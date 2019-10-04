<?php

namespace App\Console\Commands;

use App\BackUp;
use Illuminate\Console\Command;

class MakeBackUp extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'backup:make';
	
	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Make ';
	
	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle()
	{
		$backUp = BackUp::make();
		
		if ($backUp->success) {
			$this->info(sprintf('Backup have been successfully saved at %s', $backUp->file));
		} else {
			$this->error(sprintf('Something went wrong. Finished with message: %s', $backUp->message));
		}
	}
}
