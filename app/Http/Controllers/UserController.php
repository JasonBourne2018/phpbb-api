<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserGroup;
use Illuminate\Support\Facades\DB;

define('ACL_YES', 1);
define('ACL_NEVER', 0);

class UserController extends Controller
{

    private $acl_options = [];

    public function register(Request $request)
    {

        // 输入数据
        $inputArr = [
            'username'          => utf8_clean_string($request->Param['Name']),
            'user_password'     => $request->Param['Pass'],
            'user_email'        => $request->Param['Mail']
        ];

        // 验证规则
        $rule = [
            'username'  =>  'required|between:3,20|alpha_num|unique:users,username_clean',
            'user_email' =>  'required|email',
            'user_password'  =>  'required|between:3,20'
        ];
        $validator = Validator::make($inputArr, $rule);
        if ($validator->fails()) {
            return response()->json(['code'=>'1','result'=>$validator->errors()->toArray()]);
        }

        // 初始化用户信息
        $sql_ary = $data = array(
            'username'          => $request->Param['Name'],
            'username_clean'    => utf8_clean_string($request->Param['Name']),
            'user_password'     => password_hash($request->Param['Pass'], PASSWORD_ARGON2ID,['memory_cost'=>1024,'time_cost'=>2,'threads'=>2]),
            'user_email'             => strtolower($request->Param['Mail']),
            'lang'              => 'en',
            'tz'                => timezone_name_get(timezone_open('Asia/Shanghai')),
            'user_regdate'      => time(),
            'user_ip'               => $request->getClientIp(),
            'user_inactive_reason'  => 0,
            'user_inactive_time'    => 0,
            'group_id'              => 2,
            'user_type'             => 0,
            'user_actkey'           => '',
        );

        $additional_vars = array(
            'user_permissions'  => '',
            'user_timezone'     => 'UTC',
            'user_dateformat'   => 'D M d, Y g:i a',
            'user_lang'         => 'en',
            'user_style'        => 1,
            'user_actkey'       => '',
            'user_ip'           => '',
            'user_regdate'      => time(),
            'user_passchg'      => time(),
            'user_options'      => 230271,
            'user_new'          => 0,
            'user_inactive_reason'  => 0,
            'user_inactive_time'    => 0,
            'user_lastmark'         => time(),
            'user_lastvisit'        => 0,
            'user_lastpost_time'    => 0,
            'user_lastpage'         => '',
            'user_posts'            => 0,
            'user_colour'           => '',
            'user_avatar'           => '',
            'user_avatar_type'      => '',
            'user_avatar_width'     => 0,
            'user_avatar_height'    => 0,
            'user_new_privmsg'      => 0,
            'user_unread_privmsg'   => 0,
            'user_last_privmsg'     => 0,
            'user_message_rules'    => 0,
            'user_full_folder'      => 0,
            'user_emailtime'        => 0,
            'user_notify'           => 0,
            'user_notify_pm'        => 1,
            'user_notify_type'      => 0,
            'user_allow_pm'         => 1,
            'user_allow_viewonline' => 1,
            'user_allow_viewemail'  => 1,
            'user_allow_massemail'  => 1,
            'user_sig'                  => '',
            'user_sig_bbcode_uid'       => '',
            'user_sig_bbcode_bitfield'  => '',
            'user_form_salt'            => unique_id(),
        );

        foreach ($additional_vars as $key => $default_value)
        {
            $sql_ary[$key] = (isset($data[$key])) ? $data[$key] : $default_value;
        }

        $user = User::create($sql_ary);
        if (!$user) {
            return response()->json(['code'=>'1','result'=>'插入数据库失败']);
        }


        // 添加用户到已注册用户组
        $group_data = [
            'user_id'       => (int) $user->user_id,
            'group_id'      => 2,
            'user_pending'  => 0,
        ];
        UserGroup::create($group_data);

        // 添加用户到新用户组
        $group_data = [
            'user_id'       => (int) $user->user_id,
            'group_id'      => 7,
            'user_pending'  => 0,
        ];
        UserGroup::create($group_data);

        $notification = [
            'item_type' => 'notification.type.topic',
            'item_id'   => 0,
            'user_id'   => $user->user_id,
            'method'    => 'notification.method.email',
            'notify'    => 1
        ];
        UserNotification::create($notification);

        // 设置用户操作权限选项
        $sql = 'SELECT auth_option_id, auth_option, is_global, is_local
				FROM phpbb_acl_options 
				ORDER BY auth_option_id';
        $res = DB::select($sql);

        if ($res) {
            $res = json_decode(json_encode($res),true);

            $global = $local = 0;
			$this->acl_options = array();
			foreach($res as $value) {
			    if ($value['is_global'])
				{
					$this->acl_options['global'][$value['auth_option']] = $global++;
				}

				if ($value['is_local'])
				{
					$this->acl_options['local'][$value['auth_option']] = $local++;
				}

				$this->acl_options['id'][$value['auth_option']] = (int) $value['auth_option_id'];
				$this->acl_options['option'][(int) $value['auth_option_id']] = $value['auth_option'];
            }
        }

        $hold_ary = [];
        $res = DB::table('acl_roles_data')->select('*')->orderBy('role_id')->get();

        if ($res) {
            $res = json_decode(json_encode($res), true);
            foreach ($res as $value) {
                $role_cache[$value['role_id']][$value['auth_option_id']] = (int) $value['auth_setting'];
            }
            foreach ($role_cache as $role_id => $role_options)
			{
				$role_cache[$role_id] = serialize($role_options);
			}
        }

        // Grab user-specific permission settings
        $sql = "select forum_id, auth_option_id, auth_role_id, auth_setting from phpbb_acl_users where user_id= :id";
        $res = DB::select($sql, [':id'=>$user->user_id]);
        if ($res) {
            $res = json_decode(json_encode($res), true);
            foreach ($res as $value) {
                // If a role is assigned, assign all options included within this role. Else, only set this one option.
                if ($value['auth_role_id'])
                {
                    $hold_ary[$value['forum_id']] = (empty($hold_ary[$value['forum_id']])) ? unserialize($role_cache[$value['auth_role_id']]) : $hold_ary[$value['forum_id']] + unserialize($role_cache[$value['auth_role_id']]);
                }
                else
                {
                    $hold_ary[$value['forum_id']][$value['auth_option_id']] = $value['auth_setting'];
                }
            }
        }

        // Now grab group-specific permission settings
        $sql = 'SELECT a.forum_id, a.auth_option_id, a.auth_role_id, a.auth_setting
			FROM phpbb_acl_groups  a,  phpbb_user_group  ug,  phpbb_groups  g
			WHERE a.group_id = ug.group_id
				AND g.group_id = ug.group_id
				AND ug.user_pending = 0
				AND NOT (ug.group_leader = 1 AND g.group_skip_auth = 1)
				AND ug.user_id = :user_id';
        $res = DB::select($sql, [':user_id'=>$user->user_id]);

        if ($res) {
            $res = json_decode(json_encode($res), true);
            foreach ($res as $value) {
                if (!$value['auth_role_id'])
                {
                    $this->_set_group_hold_ary($hold_ary[$value['forum_id']], $value['auth_option_id'], $value['auth_setting']);
                }
                else if (!empty($role_cache[$value['auth_role_id']]))
                {
                    foreach (unserialize($role_cache[$value['auth_role_id']]) as $option_id => $setting)
                    {
                        $this->_set_group_hold_ary($hold_ary[$value['forum_id']], $option_id, $setting);
                    }
                }
            }
        }

        // 把用户权限组合成二进制并进一步转换成字符串
        $hold_str = $this->build_bitstring($hold_ary);
        $user->user_permissions = $hold_str;
        $res = $user->save();

        if ($res) {
            return response()->json(['code'=>'0','result'=>'success']);
        } else {
            return response()->json(['code'=>'1','result'=>'false']);;
        }

    }

    /**
	* Build bitstring from permission set
	*/
	function build_bitstring(&$hold_ary)
	{
		$hold_str = '';

		if (count($hold_ary))
		{
			ksort($hold_ary);

			$last_f = 0;

			foreach ($hold_ary as $f => $auth_ary)
			{
				$ary_key = (!$f) ? 'global' : 'local';

				$bitstring = array();
				foreach ($this->acl_options[$ary_key] as $opt => $id)
				{
					if (isset($auth_ary[$this->acl_options['id'][$opt]]))
					{
						$bitstring[$id] = $auth_ary[$this->acl_options['id'][$opt]];

						$option_key = substr($opt, 0, strpos($opt, '_') + 1);

						// If one option is allowed, the global permission for this option has to be allowed too
						// example: if the user has the a_ permission this means he has one or more a_* permissions
						if ($auth_ary[$this->acl_options['id'][$opt]] == ACL_YES && (!isset($bitstring[$this->acl_options[$ary_key][$option_key]]) || $bitstring[$this->acl_options[$ary_key][$option_key]] == ACL_NEVER))
						{
							$bitstring[$this->acl_options[$ary_key][$option_key]] = ACL_YES;
						}
					}
					else
					{
						$bitstring[$id] = ACL_NEVER;
					}
				}

				// Now this bitstring defines the permission setting for the current forum $f (or global setting)
				$bitstring = implode('', $bitstring);

				// The line number indicates the id, therefore we have to add empty lines for those ids not present
				$hold_str .= str_repeat("\n", $f - $last_f);

				// Convert bitstring for storage - we do not use binary/bytes because PHP's string functions are not fully binary safe
				for ($i = 0, $bit_length = strlen($bitstring); $i < $bit_length; $i += 31)
				{
					$hold_str .= str_pad(base_convert(str_pad(substr($bitstring, $i, 31), 31, 0, STR_PAD_RIGHT), 2, 36), 6, 0, STR_PAD_LEFT);
				}

				$last_f = $f;
			}
			unset($bitstring);

			$hold_str = rtrim($hold_str);
		}

		return $hold_str;
	}

    /**
	* Private function snippet for setting a specific piece of the hold_ary
	*/
	function _set_group_hold_ary(&$hold_ary, $option_id, $setting)
	{
		if (!isset($hold_ary[$option_id]) || (isset($hold_ary[$option_id]) && $hold_ary[$option_id] != ACL_NEVER))
		{
			$hold_ary[$option_id] = $setting;

			// If we detect ACL_NEVER, we will unset the flag option (within building the bitstring it is correctly set again)
			if ($setting == ACL_NEVER)
			{
				$flag = substr($this->acl_options['option'][$option_id], 0, strpos($this->acl_options['option'][$option_id], '_') + 1);
				$flag = (int) $this->acl_options['id'][$flag];

				if (isset($hold_ary[$flag]) && $hold_ary[$flag] == ACL_YES)
				{
					unset($hold_ary[$flag]);

                /*	This is uncommented, because i suspect this being slightly wrong due to mixed permission classes being possible
					if (in_array(ACL_YES, $hold_ary))
					{
						$hold_ary[$flag] = ACL_YES;
					}*/
				}
			}
		}
	}
}
