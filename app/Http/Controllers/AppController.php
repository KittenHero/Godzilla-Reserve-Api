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
     * get next week program
     *
     * @var $request Request
     * @return string
     */
    public function nextWeek(Request $request) {
        return $this->weekProgram($request, 'btnnextweek1');
    }

    /**
     * get previous week program
     *
     * @var $request Request
     * @return string
     */
    public function previousWeek(Request $request) {
        return $this->weekProgram($request, 'btnPriWeek1');
    }

    /**
     * get weekly program by command
     *
     * @var $request Request
     * @var $command string
     * @return string
     */
    public function weekProgram($request, $command) {
        $json = new stdClass();

        // validate user data
        $validator = Validator::make(Input::all(), [
            'installation_id' => 'required',
        ]);

        if ($validator->fails()) {
            $json->success = false;
            return json_encode($json);
        }

        // select user craps
        $user = User::where('installation_id', $request->installation_id)->first();

        // parse cookies
        $jar = AppController::stringCookieToCookieJar($user->cookies, AuthController::nakedUrl);

        // declare client with base url and set cookies
        $client = new Client([
            'base_uri' => AuthController::baseUrl,
            'cookies' => $jar,
        ]);

        $data = json_decode($user->data);

        // post them with user data
        $res = $client->request('post', 'Reserve.aspx', [
            'allow_redirects' => true,
            'form_params' => [
                '__EVENTTARGET' => $command,
                '__VIEWSTATE' => $data->__VIEWSTATE,
                '__VIEWSTATEGENERATOR' => $data->__VIEWSTATEGENERATOR,
                '__EVENTVALIDATION' => $data->__EVENTVALIDATION,
                '__EVENTARGUMENT' => '',
                '__VIEWSTATEENCRYPTED' => '',
            ]
        ]);

        $res = $res->getBody()->getContents();

        AppController::updateData($user, $res);
        AppController::updateCookies($user, $jar);

        $data = AppController::grabUserData($res);
        $json->name = trim($data['name']);
        $json->card = $data['card'];
        $json->charge = $data['charge'];
        $json->date = $data['date'];

        $json->program = $this->grabUserProgram($data['date'], $res);
        $user->cookies = json_encode($jar->toArray());
        $user->save();

        return json_encode($json);
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
        preg_match_all("/'Ghaza.aspx\?date=(.+?)'/", $lastRes, $out);
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
        preg_match_all("/name=\"Ghaza(\S)(\d)\".+?value=\"(\d)\".+?menu\">(.+?)<\/span.+?Edit\S\d.+?value=\"(\d)\"/s", $lastRes, $out);

        for ($i = 0; $i < count($out[1]); $i++) {
            $meal = AppController::mealIdToString($out[1][$i]);
            $id = $out[2][$i];

            $day = AppController::numberDayToStringDay($id);
            $reserved = ($out[3][$i] == '0') ? false : true;
            $available = AppController::contains($out[4][$i], 'SelectGhaza');
            $self = $out[5][$i];

            $program[$id]['id'] = (int)$id;
            $program[$id]['name'] = AppController::numberDayToStringDay($id);

            $program[$id][$meal] = [
                'reserved' => $reserved,
                'available' => $available,
                'self' => (int)$self,
            ];
        }

        // declare client with base url
        $client = new Client([
            'base_uri' => AuthController::baseUrl,
        ]);

        // grab food program
        $foodListRes = $client->request('GET', 'Ghaza.aspx?date=' . $date);
        $foodListRes = $foodListRes->getBody()->getContents();
        preg_match_all("/BulletedList(\S)(\d)\".+?<li>(.+?)<\/li>/s", $foodListRes, $out);
        // populate food program
        for ($i = 0; $i < count($out[1]); $i++) {
            $meal = AppController::mealIdToString($out[1][$i]);
            $id = $out[2][$i];
            $id++;
            $food = trim($out[3][$i]);
            if (strlen($food) < 2)
                $food = 'صبحانه';

            $program[$id][$meal]['name'] = $food;
        }

        $program = array_values($program);

        return $program;
    }

    /**
     * update cookies
     *
     * @var $user User
     * @var $cookieJar CookieJar
     * @return void
     */
    public static function updateCookies($user, $cookieJar) {
        $user->cookies = json_encode($cookieJar->toArray());
        $user->save();
    }

    /**
     * grab data for saving
     *
     * @var $user User
     * @return void
     */
    public static function updateData($user, $source) {
        preg_match_all("/id=\"__VIEWSTATE\" value=\"(.+?)\" \/>/", $source, $out);
        $__VIEWSTATE = $out[1][0];

        preg_match_all("/id=\"__VIEWSTATEGENERATOR\" value=\"(.+?)\" \/>/", $source, $out);
        $__VIEWSTATEGENERATOR = $out[1][0];

        preg_match_all("/id=\"__EVENTVALIDATION\" value=\"(.+?)\" \/>/", $source, $out);
        $__EVENTVALIDATION = $out[1][0];

        $data = [
            '__VIEWSTATE' => $__VIEWSTATE,
            '__VIEWSTATEGENERATOR' => $__VIEWSTATEGENERATOR,
            '__EVENTARGUMENT' => '',
            '__VIEWSTATEENCRYPTED' => '',
            '__EVENTVALIDATION' => $__EVENTVALIDATION,

        ];

        $user->data = json_encode($data);
        $user->save();
    }

    /**
     * parse string cookie to cookie jar
     *
     * @var $cookies string
     * @var $url string
     * @return CookieJar
     */
    public static function stringCookieToCookieJar($cookies, $url) {
        $cookies = json_decode($cookies);

        $parsedCookies = [];
        foreach ($cookies as $cookie) {
            $parsedCookies[$cookie->Name] = $cookie->Value;
        }

        return CookieJar::fromArray($parsedCookies, $url);
    }

    /**
     * parse meal id to string
     *
     * @var $meal string
     * @return string
     */
    public static function mealIdToString($meal) {
        switch ($meal) {
            case 'S':
                return 'dinner';
            case 'N':
                return 'lunch';
            case 'C':
                return 'breakfast';
        }

        return '';
    }

    /**
     * parse day id to string
     *
     * @var $day string
     * @return string
     */
    public static function numberDayToStringDay($day) {
        switch ($day) {
            case '1':
                return 'شنبه';
            case '2':
                return 'يکشنبه';
            case '3':
                return 'دوشنبه';
            case '4':
                return 'سه شنبه';
            case '5':
                return 'چهارشنبه';
            case '6':
                return 'پنج شنبه';
            case '7':
                return 'جمعه';
        }

        return '';
    }

    /**
     * check if a string contains a specific word
     *
     * @var $var string
     * @var $needle string
     * @return boolean
     */
    public static function contains($var, $needle) {
        if (strpos($var, $needle) !== false)
            return true;
        else
            return false;
    }
}
