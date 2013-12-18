<?php

/**
 * Sticky Notes
 *
 * An open source lightweight pastebin application
 *
 * @package     StickyNotes
 * @author      Sayak Banerjee
 * @copyright   (c) 2013 Sayak Banerjee <mail@sayakbanerjee.com>
 * @license     http://www.opensource.org/licenses/bsd-license.php
 * @link        http://sayakbanerjee.com/sticky-notes
 * @since       Version 1.0
 * @filesource
 */

/**
 * ShowController
 *
 * This controller handles displaying of a paste
 *
 * @package     StickyNotes
 * @subpackage  Controllers
 * @author      Sayak Banerjee
 */
class ShowController extends BaseController {

	/**
	 * Displays the default view page
	 *
	 * @access public
	 * @param  string  $urlkey
	 * @param  string  $hash
	 * @param  string  $action
	 * @return \Illuminate\Support\Facades\View|\Illuminate\Support\Facades\Redirect|null
	 */
	public function getPaste($urlkey, $hash = '', $action = '')
	{
		$paste = Paste::where('urlkey', $urlkey)->first();

		$owner = Auth::check() AND (Auth::user()->admin OR Auth::user()->id == $paste->authorid);

		// Paste was not found
		if (is_null($paste))
		{
			App::abort(404);
		}

		// We do not make password prompt mandatory for owners
		if ( ! $owner)
		{
			// Require hash to be passed for private pastes
			if ($paste->private AND $paste->hash != $hash)
			{
				App::abort(401); // Unauthorized
			}

			// Check if paste is password protected and user hasn't entered
			// the password yet
			if ($paste->password AND ! Session::has('paste.password'.$paste->id))
			{
				return View::make('site/password', array());
			}
		}

		// Increment the hit counter
		if ( ! Session::has('paste.viewed'.$paste->id))
		{
			$paste->hits++;

			$paste->save();

			Session::put('paste.viewed'.$paste->id, TRUE);
		}

		// Let's do some action!
		switch ($action)
		{
			case 'toggle':
				if ($owner)
				{
					Revision::where('urlkey', $paste->urlkey)->delete();

					$paste->private = $paste->private ? 0 : 1;

					$paste->password = NULL;

					$paste->save();
				}

				break;

			case 'shorten':
				die("Short url here");

			case 'raw':
				$response = Response::make($paste->data);

				$response->header('Content-Type', 'text/plain');

				return $response;

			default:
				return View::make('site/show', array('paste' => $paste));
		}

		// If we are here, we should get outta here quickly!
		return Redirect::to(URL::previous());
	}

	/**
	 * Handles the paste password submission
	 *
	 * @param  string  $urlkey
	 * @param  string  $hash
	 * @return \Illuminate\Support\Facades\Redirect|null
	 */
	public function postPassword($urlkey, $hash = '')
	{
		$paste = Paste::where('urlkey', $urlkey)->first();

		if ( ! is_null($paste) AND Input::has('password'))
		{
			$entered = Input::get('password');

			if (PHPass::make()->check('Paste', $entered, $paste->salt, $paste->password))
			{
				Session::put('paste.password'.$paste->id, TRUE);

				return Redirect::to("{$urlkey}/{$hash}");
			}
		}

		// Something wrong here
		App::abort(401);
	}

	/**
	 * Shows a diff between two pastes
	 *
	 * @param  string  $oldkey
	 * @param  string  $newkey
	 * @return void
	 */
	public function getDiff($oldkey, $newkey)
	{
		// Generate the paste differences
		$diff = PHPDiff::make()->compare($oldkey, $newkey);

		// Build the view data
		$data = array(
			'diff'      => $diff,
			'oldkey'    => $oldkey,
			'newkey'    => $newkey,
		);

		return View::make('site/diff', $data);
	}

}
