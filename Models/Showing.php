<?php

namespace App;

use App\Ticket;
use App\Ticket_Item;
use App\Ticket_Variation;
use DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Showing.
 *
 * @property-read \App\Event $event
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Ticket[] $tickets
 * @mixin \Eloquent
 * @property int $id
 * @property string $time
 * @property string $open
 * @property string $close
 * @property int $event_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @method static Builder|Showing whereId($value)
 * @method static Builder|Showing whereTime($value)
 * @method static Builder|Showing whereOpen($value)
 * @method static Builder|Showing whereClose($value)
 * @method static Builder|Showing whereEventId($value)
 * @method static Builder|Showing whereCreatedAt($value)
 * @method static Builder|Showing whereUpdatedAt($value)
 * @property string $seatio_id
 * @method static \Illuminate\Database\Query\Builder|\App\Showing whereSeatioId($value)
 * @property bool $email_sent
 * @method static \Illuminate\Database\Query\Builder|\App\Showing whereEmailSent($value)
 */
class Showing extends Model
{
	/**#@+
	 * Close Type constants
	 * @const string
	 */
	const CLOSE_TYPE_CUSTOM = 'custom';
	const CLOSE_TYPE_DAY = 'day';
	const CLOSE_TYPE_TWO_HOURS = 'two_hours';
	const CLOSE_TYPE_START = 'start_time';
	/**#@-*/
	
	/** @const array */
	const CLOSE_TYPES = [
		self::CLOSE_TYPE_DAY => 24,
		self::CLOSE_TYPE_START => 0,
		self::CLOSE_TYPE_TWO_HOURS => 2
	];
	
	/** @var string */
	protected $table = 'showings';
	
	/** @var bool */
	public $timestamps = true;
	
	/**
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function event()
	{
		return $this->belongsTo(\App\Event::class, 'event_id');
	}
	
	/**
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 */
	public function tickets()
	{
		return $this->hasMany(\App\Ticket::class, 'showing_id');
	}
	
	/**
	 * @param array $data
	 * @param bool $isSimple
	 * @param null|string $timezone
	 */
	public function createShowings($data, $isSimple, $timezone = null)
	{
		foreach ($data->showings as $showingData) {
			$eventDate = Carbon::createFromFormat(countryDateFormat(), $showingData->datetime, $timezone);
			if ($timezone) {
				$eventDate->setTimezone(new \DateTimeZone(date_default_timezone_get()));
			}
			
			$stopSellDate = clone $eventDate;
			if ($data->close_type !== self::CLOSE_TYPE_CUSTOM) {
				$stopSellDate->subHours(self::CLOSE_TYPES[$data->close_type]);
			} else {
				$stopSellDate = Carbon::createFromFormat(countryDateFormat(), $data->event_close_time, $timezone);
			}
			
			
			$showing = new Showing();
			$showing->close = $stopSellDate;
			$showing->time = $eventDate;
			$showing->open = Carbon::now();
			$showing->event_id = session('create_event_id');
			$showing->save();
			
			if ($isSimple) {
				$ticket = new Ticket();
				$ticket->createTickets($showingData->tickets, $showing->id);
			}
		}
	}
	
	/**
	 * @param int $showingId
	 * @param null|int $companyUrl
	 * @param null|int $eventUrl
	 * @return array
	 */
	public static function admissions($showingId, $companyUrl = null, $eventUrl = null)
	{
		$data = DB::table('ticket_items')
			->select('ticket_items.id as id')
			->addSelect(
				'ticket_items.seatio_id as seat',
				'order_items.title as ticket_type',
				'ticket_items.reservation_variant as variation',
				'ticket_items.reservation_price as variation_price'
			)
			->addSelect('tickets.name as ticket_type_default')
			->addSelect('order_items.price as price')
			->addSelect('users.email as email')
			->addSelect('ticket_items.is_sold as is_sold')
			->addSelect('ticket_items.unavailable as is_unavailable')
			->addSelect('users.name as buyer')
			->addSelect('orders.id as order_id')
			->addSelect('ticket_items.uuid as reference')
			->leftJoin('tickets', 'tickets.id', '=', 'ticket_items.ticket_id')
			->leftJoin('order_items', 'order_items.ticket_item_id', '=', 'ticket_items.id')
			->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')
			->leftJoin('users', 'users.id', '=', 'orders.user_id')
			->leftJoin('showings', 'showings.id', '=', 'tickets.showing_id')
			->leftJoin('events', 'events.id', '=', 'showings.event_id')
			->leftJoin('companys', 'companys.id', '=', 'events.company_id')
			->where('showings.id', '=', $showingId)
			->where('companys.id', '=', auth()->user()->company->id);
		
		if (!is_null($companyUrl) && !is_null($eventUrl)) {
			$data->where('companys.pretty-url', '=', $companyUrl)
				->where('events.pretty-url', '=', $eventUrl);
		}
		
		return $data->get();
	}
	
	/**
	 * @param Event $event
	 * @param null|int $showingId
	 * @return bool
	 */
	public static function generateShowingSeatioId(Event $event, $showingId = null)
	{
		foreach ($event->showings as $showing) {
			if (!is_null($showingId) && $showing->id !== $showingId) {
				continue;
			}
			$seatsio = new Seatsio();
			$seatioId = $showing->id . '-' . md5(uniqid($showing->id, true));
			
			if (!$seatsio->createEvent($event->seatio_id, $seatioId)) {
				return false;
			}
			$showing->seatio_id = $seatioId;
			$showing->save();
		}
	}
	
	/**
	 * @param Event $event
	 * @param null|int $showingId
	 */
	public static function clearTickets(Event $event, $showingId = null)
	{
		if ($showingId) {
			Ticket::whereShowingId($showingId)->delete();
		} else {
			$event->tickets->delete();
		}
	}
	
	/**
	 * Check about showing sales.
	 * @return bool
	 */
	public function hasSales()
	{
		$result = Ticket_Item::whereHas(
			'ticket.showing',
			function ($query) {
				$query->where('showings.id', $this->id);
			}
		)->where(function ($q) {
			$q->where('is_sold', true)->orWhere('refund_status', true);
		})
			->count();
		
		return (bool)$result;
	}
	
	/**
	 * @return string
	 */
	public function getTimezoneTimeAttribute()
	{
		$showingTime = shiftTimezone($this->time, $this->event->location);
		
		return $showingTime->toDateTimeString();
	}
}
