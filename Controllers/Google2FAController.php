<?php

namespace App\Http\Controllers;

use App\Http\Requests\ValidateSecretRequest;
use App\User;
use Carbon\Carbon;
use Google2FA;
use App\Http\Requests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class Google2FAController extends Controller
{
	/** @const int */
	const QR_SIZE = 300;
	
	/** @var \Illuminate\Contracts\Auth\Authenticatable|null */
	protected $user;
	
	/**
	 * Google2FAController constructor.
	 */
	public function __construct()
	{
		$this->middleware('auth');
		$this->middleware('isAdmin');
		$this->user = auth()->user();
	}
	
	/**
	 * @param Request $request
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
	 */
	public function enableTwoFactor(Request $request)
	{
		$haveKey = !is_null($this->user->google2fa_secret);
		$secretKey = Google2FA::generateSecretKey();
		$imageQR = Google2FA::getQRCodeGoogleUrl(
			$request->getHttpHost(),
			$this->user->email,
			$secretKey,
			self::QR_SIZE
		);
		
		return view('google2fa.master', compact('imageQR', 'secretKey', 'haveKey'));
	}
	
	/**
	 * @param Request $request
	 * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
	 */
	public function successEnabling(Request $request)
	{
		$secretKey = $request->get('secret_key');
		
		auth()->logout();
		
		return redirect(app_route('login'));
	}
	
	/**
	 * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|\Illuminate\View\View
	 */
	public function getValidateToken()
	{
		if (session('2fa:user:id')) {
			return view('auth.validate');
		}
		
		return redirect(app_route('login'));
	}
	
	/**
	 * @param Request $request
	 * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
	 */
	public function regenerateKey(Request $request)
	{
		$this->user->regenerate2fa();
		
		return redirect(app_route('2fa.enable'));
	}
	
	/**
	 * @param ValidateSecretRequest $request
	 * @return \Illuminate\Http\RedirectResponse
	 */
	public function postValidateToken(ValidateSecretRequest $request)
	{
		$redirectAdminPath = route('admin.dashboard');
		$userId = $request->session()->pull('2fa:user:id');
		if (Carbon::now()->subMinutes(User::SESSION_2FA_TIME)->toDateTimeString() <= session('2fa:user:create')) {
			auth()->loginUsingId($userId);
		} else {
			session()->flash('error', 'Your 2FA session has expired. Please re-login to enter otp-code again');
			$redirectAdminPath = route('login');
		}
		
		session()->forget('2fa:user:id');
		session()->forget('2fa:user:create');
		
		return redirect()->intended($redirectAdminPath);
	}
}
