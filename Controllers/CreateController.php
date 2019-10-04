<?php

namespace App\Http\Controllers\Event\Create;

use App\Group_Ticket;
use App\Group_Ticket_Variation;
use Illuminate\Support\Facades\Log;
use App\Event;
use Validator;
use App\Ticket;
use App\Showing;
use App\Location;
use Carbon\Carbon;
use App\EventComment;
use App\EventProgress;
use App\Http\Requests;
use App\Ticket_Variation;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use App\Jobs\CreateTicketItems;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class CreateController extends Controller
{
	/** @const array */
	const ALLOWED_TAGS = ['HTML.Allowed' => 'b,strong,i,em,u,p,br'];
	
	/**
	 * @var array
	 */
	public $accountSuccess = [
		'upgrade' => 'Account upgrade successfully.',
		'create' => 'Account successfully created.',
	];
	
	/**
	 * CreateController constructor.
	 */
	public function __construct()
	{
		$this->middleware('CheckIsSeller');
	}
	
	/**
	 * @param int|null $free
	 * @param bool $continue
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function view($free = null, $continue = true)
	{
		
		if (!in_array($free, Event::PAID_TYPES, true)) {
			$free = null;
		}
		
		session(['progress' => null]);
		session(['free_event' => $free]);
		
		if (Event::haveUncompleteEvents() && $continue) {
			return redirect(app_route('choose-progress'));
		} elseif (!is_null(session('free_event'))) {
			return view('event.create.metronic.master');
		}
		
		if (session()->get('success.type')) {
			session()->flash('message', $this->accountSuccess[session()->pull('success.type')]);
			session()->put('success.type', null);
		}
		
		return view('event.create.metronic.choose_event_type.master');
	}
	
	/**
	 * @param Request $request
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function viewWithProgress(Request $request)
	{
		session(['progress' => $request->get('progress_id')]);
		session(['free_event' => $request->get('event_type')]);
		
		if (is_null($request->get('progress_id'))) {
			return redirect(app_route('create-event'));
		}
		
		return view('event.create.metronic.master');
	}
	
	/**
	 * @param null|string $type
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function viewWithoutProgress($type = null)
	{
		$free_event = null;
		if (!is_null($type) && array_key_exists($type, Event::PAID_TYPES)) {
			$free_event = Event::PAID_TYPES[$type];
		}
		
		return $this->view($free_event, false);
	}
	
	/**
	 * @param Request $request
	 * @return bool
	 */
	public function saveProgress(Request $request)
	{
		$progress = EventProgress::findOrNew($request->get('id'));
		
		$result = $progress->fill($request->all());
		
		if ($result) {
			session()->flash('message', 'Progress has been saved successfully');
		}
		
		return response()->json($result);
	}
	
	/**
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function chooseProgress()
	{
		$progress = EventProgress::getAllProgress()->get();
		$count = $progress->count();
		
		return view('event.create.metronic.event-progress.master')
			->with(compact('progress', 'count'));
	}
	
	/**
	 * @param Request $request
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function deleteUncompleteEvent(Request $request)
	{
		$result = EventProgress::whereId($request->get('progress_id'))->delete();
		
		if ($result) {
			session('message', 'Progress has been deleted successfully');
		}
		
		if (Event::haveUncompleteEvents()) {
			return back();
		}
		
		return redirect(route('create-event'));
	}
	
	/**
	 * @param Request $request
	 * @return array
	 */
	public function getProgress(Request $request)
	{
		$data = [];
		$eventProgress = EventProgress::getAllProgress()->whereId($request->get('id'))->first();
		
		if ($eventProgress) {
			$data = $eventProgress->data;
			session(['free_event' => $eventProgress->free]);
		} else {
			session(['progress' => null]);
		}
		
		return response()->json($data);
	}
	
	/**
	 * @param Request $request
	 * @param Event $event
	 * @param Showing $showing
	 * @return string
	 * @throws \Exception
	 */
	public function saveEvent(EventDataValidationRequest $request, Event $event, Showing $showing)
	{
		$data = json_decode($request->get('data'));
		$free = $data->free;
		
		try {
			
			$event->createEvent($data, $free);
			
			$isSimple = $event->type_id == Event::TYPE_SIMPLE;
			$timezone = $event->location->timezone ? new \DateTimeZone($event->location->timezone) : null;
			
			$showing->createShowings($data, $isSimple, $timezone);
			
			if ($isSimple) {
				$event->publishEvent();
			} else {
				$eventComment = new EventComment();
				$eventComment->createComment($data->comments);
			}
			
		} catch (\Exception $e) {
			$event->delele();
			Log::error($e->getMessage());
			session()->flash('error', __('create_event.errors.during_saving'));
			
			return response()->json(false);
		}
		
		if (!is_null(session('progress'))) {
			EventProgress::whereId(session('progress'))->delete();
			session(['progress' => null]);
		}
		
		session()->flash('message', 'Event has been saved successfully');
		return response()->json(true);
	}
}
