<?php

namespace li3_simple_acl\extensions\helper;
use lithium\security\Auth;

/**
 * Usage, in a view:
 * <?=$this->user->fullName(); ?>
 * <?=$this->user->info(); ?>
 */
class User extends \lithium\template\Helper {

    public function info() {

        // $user = Auth::check('default');
        // return $user;
        return Auth::check('default');
    }

    public function fullName() {

        $userinfo = self::info();

        return $userinfo["first_name"] . " " . $userinfo["last_name"];
    }

}

?>