<?php

namespace Nova\Foundation\Auth;

use Nova\Foundation\Auth\RedirectsUsersTrait;
use Nova\Http\Request;
use Nova\Mail\Message;
use Nova\Support\Facades\Auth;
use Nova\Support\Facades\Config;
use Nova\Support\Facades\Hash;
use Nova\Support\Facades\Password;
use Nova\Support\Facades\Redirect;
use Nova\Support\Facades\View;
use Nova\Support\Str;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


trait ResetsPasswordsTrait
{
	use RedirectsUsersTrait;


	/**
	 * Display the form to request a password reset link.
	 *
	 * @return \Nova\Http\Response
	 */
	public function getEmail()
	{
		return $this->createView()
			->shares('title', __d('nova', 'Reset Password'));
	}

	/**
	 * Send a reset link to the given user.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function postEmail(Request $request)
	{
		return $this->sendResetLinkEmail($request);
	}

	/**
	 * Send a reset link to the given user.
	 *
	 * @param  \Nova\Http\Request  $request
	 * @return \Nova\Http\Response
	 */
	public function sendResetLinkEmail(Request $request)
	{
		$this->validate($request, array('email' => 'required|email'));

		//
		$broker = $this->getBroker();

		$response = Password::broker($broker)->remind($request->only('email'), function (Message $message)
		{
			$message->subject($this->getEmailSubject());
		});

		switch ($response) {
			case Password::REMINDER_SENT:
				return Redirect::back()->with('success', Config::get($response));

			case Password::INVALID_USER:
				return Redirect::back()->withErrors(array('email' => Config::get($response)));
		}
	}

	/**
	 * Get the e-mail subject line to be used for the reset link email.
	 *
	 * @return string
	 */
	protected function getEmailSubject()
	{
		if (method_exists($this, 'subject')) {
			return $this->subject();
		}

		return property_exists($this, 'subject') ? $this->subject : __d('nova', 'Your Password Reset Link');
	}

	/**
	 * Display the password reset view for the given token.
	 *
	 * @param  string  $token
	 * @return \Nova\Http\Response
	 */
	public function getReset($token = null)
	{
		if (is_null($token)) {
			throw new NotFoundHttpException;
		}

		return $this->createView()
			->shares('title', __d('nova', 'Reset Password'))
			->with('token', $token);
	}

	/**
	 * Reset the given user's password.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function postReset(Request $request)
	{
		return $this->reset($request);
	}

	/**
	 * Reset the given user's password.
	 *
	 * @param  \Nova\Http\Request  $request
	 * @return \Nova\Http\Response
	 */
	public function reset(Request $request)
	{
		$this->validate($request, array(
			'token'		=> 'required',
			'email'		=> 'required|email',
			'password'	=> 'required|confirmed|min:6',
		));

		$credentials = $request->only(
			'email', 'password', 'password_confirmation', 'token'
		);

		//
		$broker = $this->getBroker();

		$response = Password::broker($broker)->reset($credentials, function ($user, $password)
		{
			$this->resetPassword($user, $password);
		});

		switch ($response) {
			case Password::PASSWORD_RESET:
				return Redirect::to($this->redirectPath())->with('success', Config::get($response));

			default:
				return Redirect::back()
					->withInput($request->only('email'))
					->withErrors('email', Config::get($response));
		}
	}

	/**
	 * Reset the given user's password.
	 *
	 * @param  \Nova\Auth\Contracts\Reminders\RemindableInterface  $user
	 * @param  string  $password
	 * @return void
	 */
	protected function resetPassword($user, $password)
	{
		$user->password = Hash::make($password);

		$user->remember_token = Str::random(60);

		$user->save();

		//
		Auth::guard($this->getGuard())->login($user);
	}

	/**
	 * Get the name of the guest middleware.
	 *
	 * @return string
	 */
	protected function guestMiddleware()
	{
		$guard = $this->getGuard();

		return ! is_null($guard) ? 'guest:' .$guard : 'guest';
	}

	/**
	 * Get the broker to be used during password reset.
	 *
	 * @return string|null
	 */
	public function getBroker()
	{
		return property_exists($this, 'broker') ? $this->broker : null;
	}

	/**
	 * Get the guard to be used during password reset.
	 *
	 * @return string|null
	 */
	protected function getGuard()
	{
		return property_exists($this, 'guard') ? $this->guard : null;
	}
}
