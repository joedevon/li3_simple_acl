#Simple ACL for Lithium (#li3) Framework.

##IMPORTANT

I wrote this at 4am one night to handle ACLs the way I wanted to. While I
like li3_access, it wasn't really the approach I was after. So this is an
alternative. However it is an ALPHA release. I'd really like feedback. Also
please feel free to fork and send pull requests.

##Installation

Checkout the code to your library directory:

	cd /path/to/your/project {one level above app}
	git init submodule
	git add git://github.com/joedevon/li3_simple_acl.git

Include the library in in your `/app/config/bootstrap/libraries.php`

	Libraries::add('li3_simple_acl');

##How is Simple ACL different?

Many ACLs have complicated rules and various calls to Access Control. For 
example: $acl->isAdmin, $acl->isOwner, etc. This is similar to using if 
statements deep in your code to perform ACL. For example, does a user have 
permission to see the "edit" button on a post he authored:

	if($user['id'] === $post['author_id']) {
		echo 'edit';
	}

This is the same as doing: if($acl->isOwner){echo 'edit';}.

All is well and good until you need to add a new layer of Access Control, say a 
moderator. Suddenly you have to refactor every single call to acl to add 
if($acl->isOwner || $acl->isModerator).  In short, a nightmare.

You should be protecting the resource and defining permissions where the data lives. Be it a route, a database field, a row in the database, an item in the view. Then if you change rules, your code stays mostly the same.

Therefore, Simple ACL only has one call. Acl::isAllowed($user, $perms);

##Usage

Once you've followed the setup instructions, you will call the ACL thusly:

	if(Acl::isAllowed($user, $perms)) {
		echo 'open sesame'; // allowed
	} else {
		echo 'uh uh, not so fast'; // denied
	}

The $user array can be obtained with a call to Lithium's Auth Layer e.g.

	// replace 'auth_config_name' with yours, usually 'default'
	$user = Auth::check('auth_config_name');

Details on setting up Lithium's Auth are included further down. Just note that 
$user['role'] will match the $perms.

The $perms array will simply be an array of User roles allowed access. e.g.:

	$perms = array('admin', 'foo', 'moderator');

The roles are mostly defined by YOU, with the exception of: 'owner',
'user' and 'any'.

If 'any' is one of the perms, EVERYONE will be let in.
If 'user' is one of the perms, any logged in user will be let in. (This feature might go away since it's built into Lithium's Auth service).

If the 'owner' of a resource is included in the perms list, we expect an id
to be included. Example:

	$perms = array('admin', 'owner' => 1234);

In this example, if the user does not have a role of 'admin', he can still 
get access if his $user['id'] = 1234. This pattern is mostly used to protect 
a row in a database table. For a forum example:

	{
		'forum_id' : 1,
		'thread_id' : 111,
		'post_id' : 922,
		'user_id' : 1234,
		'roles' : ['admin', 'owner']		
	}

So when you create the $perms, you see in the 'roles' array that 'owner' is
listed, so you set $perms['owner'] = 1234 (aka $user_id). 

##Setup

This is the pattern I use. Modify to suit:

###Set up a User model with a `role` field

You should have a `user_id` field of some kind. If using MongoDB it would be 
`_id`. Also define a `role` field. You're welcome to call the role just about 
anything you want. Say $user['role'] = 'foo'.

In a future version I plan to support multiple `role`s, likely by just allowing 
an array of `role`s in the $user['role'] field. Probably NOT inherited roles or 
complicated role schemes.

###Set up an Auth configuration

This is part of [Lithium core](http://lithify.me/docs/lithium/security/auth/adapter/Form). I'll
give you instructions regardless. Open bootstrap.php. Make sure this line is
uncommented:

	require __DIR__ . '/bootstrap/session.php';

Then open session.php and make sure you've got a setup similar to:

	use lithium\storage\Session;
	Session::config(array(
		// 'cookie' => array('adapter' => 'Cookie'),
		'default' => array('adapter' => 'Php')
	));

	use lithium\security\Auth;
	Auth::config(array(
	    'default' => array(
	        'adapter' => 'Form',
	        'model' => 'User',
	        'fields' => array('email', 'password'),
	        'scope' => array('active' => true)
	    )
	));

You can change up the settings if you want. Above is the config that works for
me.

###Set up a Base Controller. (Optional)

I like this pattern. Set up a base controller. Therein put this bit of code:

	protected function _init() {
		// ...stuff...
		$this->userinfo = (Auth::check('default')) ?: null;
		// ...stuff...
	}

Now all extending controllers can access the $user array with a simple call to 
`$this->userinfo`.

###Set up permissions *on the resources*

This will look a bit different depending where you implement it. Trying to 
cover all bases here, I'll provide an example for routes.php, a controller, a 
model, a database row and a view. Routes are up first:

####Routes
Open routes.php:

	use li3_simple_acl\extensions\security\Acl;
	use lithium\security\Auth;

// @2do change this example to better one like, moderator

Say you want to only allow certain routes for logged in users. Do it thus:

	/**
	 * wrap all routes that require a logged-in user within this ACL
	 */
	if(Acl::isAllowed(Auth::check('default'), 'user')) {
	    Router::connect('/forum/read', array(
	        'controller' => 'app\controllers\ForumController',
	        'action' => 'read'
	    ));
		// Add more routes here
	} else {
		// force them to user login for example
	}

####Model Field via Controller
Let's say you want to skip setting the `notes` field from the Forum model.
Put this in Forum.php:

	class Forum extends \lithium\data\Model {

	    /**
	     * Holds the rules which can make some fields operate under acl
	     */
	    public static $acl = array(
			'notes' => array('admin')
	    );

	    public static function getAcls($name) {
	        return self::$acl[$name];
	    }
		// SNIP
	}

Now open the controller. Assuming you've set up a Base Controller as I
suggested, this will work:

	if (!Acl::isAllowed($this->userinfo, Forum::getAcls('notes'))) {
	    unset($data['notes']);
	}

If you don't have a base controller, you can obviously call it this way:

	if (!Acl::isAllowed(Auth::check('default'), Forum::getAcls('notes'))) {
	    unset($data['notes']);
	}

This was just an example of course.

####Database Row
See the "Usage" section for an example of a database row.

####View
Say you don't want to show that very same "notes" form field in the
forum.html.php view. The "catch" is that the userinfo in this case
is pulled from the User.php view helper. Here's the code:

	<?php if(!Acl::isAllowed(
		$this->user->info(), Activity::getAcls('global')
	)): ?>
		<?=$this->form->field('notes', array('label' => 'This is an admin only field')) ?>
	<?php endif; ?>

##Future plans

*Deny roles that are banned.
*Arrays in the $user['role'] field.
*Make it easier/more flexible to extend rules. E.g. for IPAddresses, or generic
rules that go beyond roles and are still easy to compare from $user to $perm

##Credits

###Joe Devon

Github: [joedevon](https://github.com/joedevon/li3_simple_acl)

Website: [MySQLTalk](http://www.mysqltalk.com)

Twitter: [@joedevon](http://twitter.com/joedevon)