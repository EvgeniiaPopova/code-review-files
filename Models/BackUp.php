<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Compressors\GzipCompressor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * App\BackUp.
 *
 * @property int $id
 * @property string|null $file
 * @property int|null $size
 * @property int $success
 * @property string|null $message
 * @property string $start_at
 * @property string $end_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|\App\BackUp whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\BackUp whereEndAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\BackUp whereFile($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\BackUp whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\BackUp whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\BackUp whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\BackUp whereStartAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\BackUp whereSuccess($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\BackUp whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class BackUp extends Model
{
	const PATH_BEGINNING = '/00_MASTER/';
	/** @var string */
	protected $table = 'backups';
	
	/**
	 * @return BackUp
	 */
	public static function make()
	{
		$now = Carbon::now();
		$backup = new self();
		$backup->start_at = $now;
		
		try {
			$zipFileName = "{$now->hour}:{$now->minute}:{$now->second}.gz";
			$dumpFile = tempnam(sys_get_temp_dir(), 'dump');
			
			$backUpPath = self::PATH_BEGINNING . "{$now->year}/{$now->month}/{$now->day}/{$zipFileName}";
			
			MySql::create()
				->setHost(config('database.connections.mysql.host'))
				->setDbName(config('database.connections.mysql.database'))
				->setUserName(config('database.connections.mysql.username'))
				->setPassword(config('database.connections.mysql.password'))
				->excludeTables(['backups', 'telescope_entries', 'telescope_entries_tags', 'telescope_monitoring'])
				->useCompressor(new GzipCompressor())
				->dumpToFile($dumpFile);
			
			Storage::disk('s3_backup')->put($backUpPath, file_get_contents($dumpFile));
			
			$backup->file = $backUpPath;
			$backup->size = filesize($dumpFile);
			
		} catch (\Exception $exception) {
			$backup->success = false;
			$backup->message = $exception->getMessage();
		} finally {
			if (checkIfFileExists($backUpPath, 's3_backup')) {
				$backup->success = true;
				self::deleteTmp($dumpFile);
			}
			
			$backup->end_at = Carbon::now();
			$backup->save();
			
			self::sendNotification($backup);
		}
		
		
		return $backup;
	}
	
	/**
	 * @param string $dumpFile
	 * @param string $backUpPath
	 */
	public static function deleteTmp($dumpFile)
	{
		try {
			if (file_exists($dumpFile)) {
				unlink($dumpFile);
			}
		} catch (\Exception $exception) {
			Log::error($exception->getMessage());
		}
	}
	
	/**
	 * @param BackUp $backup
	 */
	public static function sendNotification($backup)
	{
		$admins = User::whereGroupId(Group::ADMIN)->get()->pluck('email')->toArray();
		
		Mail::to($admins)->queue(new \App\Mail\BackUp($backup));
	}
}
