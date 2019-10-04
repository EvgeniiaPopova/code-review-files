<?php

namespace App\Http\Requests;

use App\User;
use Google2FA;
use App\Http\Requests\Request;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Factory as ValidationFactory;

class ValidateSecretRequest extends FormRequest
{
	/** @var User */
	private $user;
	
	/**
	 * ValidateSecretRequest constructor.
	 * @param ValidationFactory $factory
	 */
	public function __construct(ValidationFactory $factory)
	{
		$factory->extend(
			'valid_token',
			function ($attribute, $value, $parameters, $validator) {
				$secret = $this->user->google2fa_secret;
				
				return Google2FA::verifyKey($secret, $value);
			},
			'Not a valid token'
		);
	}
	
	/**
	 * Determine if the user is authorized to make this request.
	 *
	 * @return bool
	 */
	public function authorize()
	{
		try {
			$this->user = User::findOrFail(
				session('2fa:user:id')
			);
		} catch (Exception $e) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array
	 */
	public function rules()
	{
		return [
			'totp' => 'bail|required|digits:6|valid_token',
		];
	}
}
