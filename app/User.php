<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int id
 * @property string installation_id
 * @property string device
 * @property string data
 * @property string cookies
 * @property int logged_count
 * @property int run_count
 * @property string last_login
 * @property Carbon created_at
 * @property Carbon updated_at
 */
class User extends Model {

}
