<?php

namespace li3_simple_acl\extensions\security;

/**
 * `Lightweight ACL model`
 * Burden is on defining the rules where the `resources` are located:
 * `routes`, `models` and even `collection` row
 *
 * `Description`
 * `User`.`role` needs to be set in the User model
 * Then permissions need to be defined and stored at the resource level.
 *
 * In routes, simply put the list of roles allowed to access a route
 * in an array. Then call the isAllowed method.
 *
 * For the most part, the roles in the `User` collection need to match the
 * permissions. You can call them 'foo' if you like. As long as they match.
 *
 * However, there are three special permissions: `owner`, `user` and
 * 'any'.
 *
 * For `owner`, you need $perms['owner'] to match the $user['id']. If so,
 * permission is granted. See "Usage" for details.
 *
 * For `user`, permission will be granted as long as the $user array exists.
 * It's really a duplicate for the Auth::check built into Lithium so I'm
 * thinking of getting rid of this. Feedback appreciated.
 *
 * For 'any', permission will always be granted.
 *
 * @see /app/models/Activity.php for an example of setting permission on fields
 */
class Acl {

    /**
     * @var $user array (must be the user session)
     * @var $perms array
     *  Usage:
     *  $perms = array('admin', 'owner' => 444);
     *  Acl::isAllowed($user, $perms);
     * @return boolean
     */
    public static function isAllowed($user, $perms){
        $perms = (array) $perms;
        $user = (array) $user;
        // this would be used for collections with row level perms
        if (!empty($perms['owner'])) {
            if ($perms['owner'] === $user['_id']) {
                return true;
            }
            unset($perms['owner']);
        }

        sort($perms); // 'any' will go early or even first
        foreach ($perms as $rule) {
            // any
            if ('any' == $rule) {
                return true;
            }
            // match roles to rules
            //fix't warning (Ev)
            if (isset($user['role']) && $user['role'] == $rule) { // @note, beware empty user['role']s
                return true;
            }
            /**
             * logged in `user`
             * @note Ambivalent about this "feature" since Lithium Auth::check
             * does same thing out of the box
             */
            if ('user' == $rule && !empty($user)) {
                return true;
            }
        }
        return false;
    }
}