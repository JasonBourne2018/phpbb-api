<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;


class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authorizable, Authenticatable;
    //use Notifiable;

    protected $table = 'users';

    protected $primaryKey = 'user_id';

    public $timestamps = false;


    protected $fillable = [
          'user_email', 'username', 'user_password','user_permissions','user_sig',
          'username_clean',
          'user_regdate',
          'user_ip',
          'user_inactive_reason',
          'user_inactive_time',
          'group_id',
          'user_type',
          'user_actkey',
          'user_permissions',
          'user_timezone',
          'user_dateformat',
          'user_lang',
          'user_style',
          'user_passchg',
          'user_options',
          'user_new',
          'user_lastmark',
          'user_lastvisit',
          'user_lastpost_time',
          'user_lastpage',
          'user_posts',
          'user_colour',
          'user_avatar',
          'user_avatar_type',
          'user_avatar_width',
          'user_avatar_height',
          'user_new_privmsg',
          'user_unread_privmsg',
          'user_last_privmsg',
          'user_message_rules',
          'user_full_folder',
          'user_emailtime',
          'user_notify',
          'user_notify_pm',
          'user_notify_type',
          'user_allow_pm',
          'user_allow_viewonline',
          'user_allow_viewemail',
          'user_allow_massemail',
          'user_sig',
          'user_sig_bbcode_uid',
          'user_sig_bbcode_bitfield',
          'user_form_salt',
            ];

    const UPDATED_AT = null;
    const CREATED_AT = null;

    public function rules()
    {
        return [
            'username'  =>  'required',
            'user_password' =>  'required',
            'user_email'    =>  'required',
        ];
    }
}
