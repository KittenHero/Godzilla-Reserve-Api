<?php

namespace App\Http\Controllers;

use App\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use stdClass;

class AuthController extends Controller {

    const baseUrl = "http://res.gonbad.ac.ir/";
    const nakedUrl = "res.gonbad.ac.ir";

    public function init(Request $request) {
        $json = new stdClass();

        // validate user data
        $validator = Validator::make(Input::all(), [
            'installation_id' => 'required',
            'device' => 'required'
        ]);

        if ($validator->fails()) {
            $json->success = false;
            return json_encode($json);
        }

        // declare client with base url and cookies
        $client = new Client([
            'base_uri' => self::baseUrl,
            'cookies' => true
        ]);

        // get login page for grab necessary login data
        $jar = new CookieJar();
        $response = $client->request('GET', 'Login.aspx', [
            'cookies' => $jar
        ]);
        $res = $response->getBody()->getContents();

        // grab data
        preg_match_all("/<input type=\"hidden\" name=\"__VIEWSTATE\" id=\"__VIEWSTATE\" value=\"(.+?)\" \/>/", $res, $output);
        $__VIEWSTATE = $output[1][0];
        preg_match_all("/<input type=\"hidden\" name=\"__EVENTVALIDATION\" id=\"__EVENTVALIDATION\" value=\"(.+?)\" \/>/", $res, $output);
        $__EVENTVALIDATION = $output[1][0];
        preg_match_all("/<img src=\"CaptchaImage.aspx\?guid=(.+?)\" border/", $res, $output);
        $CaptchaGUID = $output[1][0];

        $data = [
            '__VIEWSTATE' => $__VIEWSTATE,
            '__EVENTVALIDATION' => $__EVENTVALIDATION,
            '__CaptchaGUID' => $CaptchaGUID
        ];

        // select user craps
        $user = User::where('installation_id', $request->installation_id)->first();

        // if user doesn't exist then create it
        if ($user == null) {
            $user = new User();
            $user->installation_id = $request->installation_id;
            $user->device = $request->device;
            $user->save();
        }

        $user->data = json_encode($data);
        $user->cookies = json_encode($jar->toArray());
        $user->run_count += 1;
        $user->save();

        $captchaName = 'captcha' . time() . '.jpg';
        $captchaPath = storage_path('app/public/' . $captchaName);

        $resource = fopen($captchaPath, 'w');
        $client->get('CaptchaImage.aspx?guid=' . $CaptchaGUID, array(
            'sink' => $resource,
            'http_errors' => false
        ));

        $json->success = true;
        $json->captcha = url('/storage/' . $captchaName);
        return json_encode($json);
    }


    public function login(Request $request) {
        $json = new stdClass();

        // validate user data
        $validator = Validator::make(Input::all(), [
            'installation_id' => 'required',
            'username' => 'required',
            'password' => 'required',
            'captcha' => 'required',
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
            'base_uri' => self::baseUrl,
            'cookies' => $jar,
        ]);

        $data = json_decode($user->data);

        // post them with user data
        $res = $client->request('post', 'Login.aspx', [
            'allow_redirects' => true,
            'form_params' => [
                '__EVENTTARGET' => '',
                '__EVENTARGUMENT' => '',
                '__VIEWSTATE' => $data->__VIEWSTATE,
                '__VIEWSTATEGENERATOR' => 'C2EE9ABB',
                '__VIEWSTATEENCRYPTED' => '',
                '__EVENTVALIDATION' => $data->__EVENTVALIDATION,
                'txtusername' => $request->username,
                'txtpassword' => $request->password,
                'CaptchaControl1' => $request->captcha,
                'btnlogin' => '',
            ]
        ]);
        $res = $res->getBody()->getContents();

        // check valid login
        if (strpos($res, 'ChangePass.aspx') !== false) {
            // login successful
            $json->success = true;

            $data = AppController::grabUserData($res);

            $json->name = $data['name'];
            $json->card = $data['card'];
            $json->charge = $data['charge'];
            $json->date = $data['date'];

            $json->program = AppController::grabUserProgram($data['date'], $res);

            // save user cookies for future requests
            $user->cookies = json_encode($jar->toArray());
            $user->logged_count += 1;
            $user->last_login = Carbon::now();
            $user->save();
        } else {
            $json->success = false;
        }

        return json_encode($json);
    }


}
