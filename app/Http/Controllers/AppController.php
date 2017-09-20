<?php

namespace App\Http\Controllers;

use App\User;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use stdClass;

class AppController extends Controller {

    /**
     * grab program from time
     *
     * @var $request Request
     * @return string
     */
    public function getProgramWithDate(Request $request) {
        $json = new stdClass();

        // validate user data
        $validator = Validator::make(Input::all(), [
            'installation_id' => 'required',
            'date' => 'required',
        ]);

        if ($validator->fails()) {
            $json->success = false;
            return json_encode($json);
        }

        // select user craps
        $user = User::where('installation_id', $request->installation_id)->first();

        $cookies = json_decode($user->cookies);
        $jar = CookieJar::fromArray($cookies, AuthController::nakedUrl);

        // declare client with base url and set cookies
        $client = new Client([
            'base_uri' => AuthController::baseUrl,
            'cookies' => $jar,
        ]);
    }

    /**
     * grab user data
     *
     * @var $lastRes string
     * @return array
     */
    public static function grabUserData($lastRes) {
        // grab user name
        preg_match_all("/<span id=\"LbFName\">(.+?)<\/span>/", $lastRes, $out);
        $name = $out[1][0];

        // grab user card number
        preg_match_all("/<span id=\"lbnumber\">(.+?)<\/span>/", $lastRes, $out);
        $card = $out[1][0];

        // grab account charge
        preg_match_all("/<span id=\"lbEtebar\">(.+?)<\/span>/", $lastRes, $out);
        $charge = $out[1][0];

        // grab current date
        preg_match_all("/Ghaza.aspx\?date=(.+?)'/", $lastRes, $out);
        $date = $out[1][0];

        return compact('name', 'card', 'charge', 'date');
    }

    /**
     * grab program array
     *
     * @var $date string
     * @var $lastRes string
     * @return array
     */
    public static function grabUserProgram($date, $lastRes) {
        // create empty array for food program
        $program = [];

        // grab user reserve data
        preg_match_all("/<input name=\"GhazaN(\d)\" type=\"text\" value=\"(\d)\"/", $lastRes, $out);
        for ($i = 0; $i < count($out[1]); $i++) {
            $id = $out[1][$i];
            $reserved = $out[2][$i];

            $program[] = [
                'id' => $id,
                'reserved' => $reserved,
            ];
        }

        // declare client with base url
        $client = new Client([
            'base_uri' => AuthController::baseUrl,
        ]);

        // grab food program
        $foodListRes = $client->request('GET', 'Ghaza.aspx?date=' . $date);
        $foodListRes = $foodListRes->getBody()->getContents();
        preg_match_all("/class=\"mainrow\">(.+?)<\/td>.+?<li>(.+?)<\/li>.+?<li>(.+?)<\/li>/is", $foodListRes, $out);

        // populate food program
        for ($i = 0; $i < count($out[1]); $i++) {
            $day = $out[1][$i];
            $lunch = $out[2][$i];
            $dinner = $out[3][$i];

            $program[$i]['day'] = $day;
            $program[$i]['lunch'] = $lunch;
            $program[$i]['dinner'] = $dinner;
        }

        return $program;
    }

    /**
     * grab data for saving
     *
     * @var $user User
     * @return void
     */
    public function updateData($user, $source) {

        preg_match_all("/id=\"__VIEWSTATE\" value=\"(.+?)\" \/>/", $source, $out);
        $__VIEWSTATE = $out[1][0];

        $data = [
            '__VIEWSTATE' => $__VIEWSTATE,
        ];

        $user->data = json_encode($data);
        $user->save();
    }
}
