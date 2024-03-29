<?php

namespace App\Libraries;

use DB;
use App\Link;
use App\Click;
use App\User;
use Jenssegers\Agent\Agent;
use Carbon;

class Shortener
{
	public function createShortLink(array $request) {
		return new Link([
			'url' 			=> $this->encodeUrl($request['url']),
			'short_url' 	=> $this->setName($request['name']),
			'description'	=> $request['description']
		]);
	}

	public function editShortLink(Link $link, array $request) {
		return $link->update([
			'url' 			=> Shortener::encodeUrl($request['url']),
			'description' 	=> $request['description']
		]);
	}

	public function deactivateShortLink(Link $link) {
		return $link->update([
			'status' => 0
		]);
	}

	public function createClick($request) {
		$agent = new Agent;

		return new Click([
			'ip' 		=> $request->ip(), // Save client IP
			'device' 	=> $agent->isMobile() ? ($agent->isTablet() ? 'tablet' : 'phone') : 'computer' // Save client device
		]);
	}

	public function getLinkStats(Link $link) {
		$stats = $link->clicks;

		return $this->generateStatsData($stats);
	}

	public function getAllLinksStats(User $user) {
		$stats = [];

		foreach ($user->links as $link) {
			foreach ($link->clicks as $click) {
				$stats[] = $click;
			}
		}

		return $this->generateStatsData($stats);
	}

	private function generateStatsData($stats) {
		// Get day from last week
		$lastWeek = $this->getLastWeek();

		foreach ($stats as $click) {
			if (isset($lastWeek[$click->created_at->format("d-m-Y")])) {
				$lastWeek[$click->created_at->format("d-m-Y")]++;
			}
		}

		$i = 0;
		foreach ($lastWeek as $day => $value) {
			if (count($lastWeek) - 1 == $i) {
				$series[] = $value;
			} else {
				$labels[] = $day;
				$series[] = $value;	
			}

			$i++;
		}

		$data = array(
			"labels" => $labels,
			"series" => [ $series ]
		);

		return json_encode($data);
	}

	// Get day from last week
	private function getLastWeek() {
		$lastWeek = [];
		for ($i = 6; $i >= 0; $i--) {
			$now = Carbon::now();
			$lastWeek[$now->subDay($i)->format("d-m-Y")] = 0;
        }

        return $lastWeek;
	}

	// Set Link's name (short url)
	private function setName($requestName) {
		// Check if name is empty, if not then generate random string
		if (!isset($requestName) || empty($requestName)) {
			return $this->generateRandomName('short_url'); // Generate random string (name)
		}
			
		return $requestName;
	}

	// Generate random string (name) for link
	private function generateRandomName($attribute, $length = 6) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

		do {
			$name = '';

		    for ($i = 0; $i < $length; $i++) {
		        $name .= $characters[rand(0, strlen($characters) - 1)];
		    }
		} while (!$this->isNameAvailable(null, $name));

	    return $name;
	}

	// Encode URL - Base64
	private function encodeUrl($url) {
		return base64_encode($url);
	}

	// Decode URL - Base64
	public static function decodeUrl($url) {
		return base64_decode($url);
	}

	// Validation
	public function isNameAvailable($attribute, $value, $parameters = null, $validator = null) {
		return DB::table('links')->where([ ['short_url', '=', $value], ['status', '=', '1'] ])->count() === 0;
	}

	public function checkIfLinkExist($short_url) {
		// Check if link exist
        if (Link::where([ ['short_url', '=', $short_url], ['status', '=', '1'] ])->count() === 0) {
            flash(0, 'Link doesn\'t exist!');

            return false;
        }

        return true;
	}

	public function checkAccessToLink(User $user, Link $link) {
		// Check if user is link's owner
        if (!$user->links($link)) {
            // User isn't owner, no access, redirect to dashboard with error
            flash(0, 'No access!');

            return false;
        }

        return true;
	}
}
