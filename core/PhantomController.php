<?php
/**
 * Application Controller Class
 *
 * This class object is the super class that every library in
 * CodeIgniter will be assigned to.
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Libraries
 * @author		EllisLab Dev Team
 * @link		http://codeigniter.com/user_guide/general/controllers.html
 */
class PhantomController {

	/**
	 * Reference to the CI singleton
	 *
	 * @var	object
	 */
	private static $instance;

	private static $dbInstance;
	/**
	 * Class constructor
	 *
	 * @return	void
	 */
	public function __construct()
	{
		self::$instance =& $this;

		// Assign all the class objects that were instantiated by the
		// bootstrap file (CodeIgniter.php) to local class variables
		// so that CI can run as one big super object.
		foreach (is_loaded() as $var => $class)
		{
			$this->$var =& load_class($class);
			log_message('debug', 'loading: ' . $class . '/' . $class . '.php');
		}

		$this->config->load('config');
		$this->load =& load_class('Loader', 'core');
		$this->load->initialize();
		log_message('debug', 'Controller Class Initialized');

		//initial db layer
		self::$dbInstance = mysqli_connect(Config::get('db.host'), Config::get('db.username'), Config::get('db.password'), Config::get('db.name'));

	}

	// --------------------------------------------------------------------

	/**
	 * Get the CI singleton
	 *
	 * @static
	 * @return	object
	 */
	public static function &get_instance()
	{
		return self::$instance;
	}

	public static function &get_dbinstance()
	{
		return self::$dbInstance;
	}

	public static function &closeConnection($instance)
	{
		mysqli_close($instance);
	}

}

/* End of file Controller.php */
/* Location: ./system/core/Controller.php */