
## Making New Layers

Making a layer is simple. Create a PHP file in the site/layers folder using this template.

    # note: <layer_name> must match the file name
    class <layer_name> extends layer
    {
        public static function run()
        {
            global $request; global $settings;
            return parent::run_next();
        }
    }

### Example

Layers provide a simple way to add user authentication to a website. Create 'authenticate.php' in the site/layers folder. This code expects a 'user' model which returns the logged-in user and blocks access to the admin controller (except for the login function) unless there is a current user. This layer must be loaded after the routing layer because it expects the routing to be set already.

    class authenticate extends layer
    {
        public static function run()
        {
            global $request; global $settings;
            if (!array_key_exists('route', $request)
                || !array_key_exists('controller', $request['route'])
                || !array_key_exists('function', $request['route'])
                || !array_key_exists('parameters', $request['route']))
            {
                throw new Exception("Authenticate layer requires routing variable to be set in \$request.");
            }
            $request['route']['parameters']['user'] = user::get_current();
            if ($request['route']['controller'] == 'admin')
            {
                if ($request['route']['function'] != 'login' && null == $request['route']['parameters']['user'])
                {
                    # user is not authenticated, so make them log in
                    redirect_to_url("login");
                }
            }
            return parent::run_next();
        }
    }
