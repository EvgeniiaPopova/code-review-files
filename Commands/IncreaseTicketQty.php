<?php

namespace App\Console\Commands;

use App\Event;
use App\Ticket;
use Dompdf\Exception;
use App\Jobs\CreateTicketItems;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class IncreaseTicketQty extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'tickets:increase {showingId} {ticketId} {quantityToAdd}';
	
	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Command to increase ticket quantity';
	
	/**
	 * @return Builder|Ticket
	 */
	protected function getTicket()
	{
		$ticket = Ticket::with('showing.event')->whereId($this->argument('ticketId'))
			->where('showing_id', $this->argument('showingId'))
			->whereHas(
				'showing.event',
				function ($query) {
					$query->where('events.type_id', Event::TYPE_SIMPLE);
				}
			)
			->firstOrFail();
		
		return $ticket;
	}
	
	/**
	 * Execute the command.
	 */
	public function handle()
	{
		$addQty = $this->argument('quantityToAdd');
		$ticket = $this->getTicket();
		
		try {
			$ticket->qty += $addQty;
			$ticket->save();
			
			dispatch(new CreateTicketItems($ticket, $addQty));
			
			$this->info(sprintf('Successfully added %s tickets', $addQty));
		} catch (Exception $e) {
			$this->warn('Something went wrong');
			Log::error($e);
		}
	}
}
