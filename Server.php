<?PHP
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2002 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Stephan Schmidt <schst@php.net>                             |
// +----------------------------------------------------------------------+
//
//    $Id$

require_once 'PEAR.php';

/**
 * PHP socket server base class
 *
 * This class must only be used to create a new server using
 * the static method 'create()'.
 *
 * To handle the events that happen while the server is running
 * you have to create a new class that handles all events.
 *
 * <code>
 * require_once 'myHandler.php';
 * require_once 'Net/Server.php';
 *
 * $server = &Net_Server::create('fork', 'localhost', 9090);
 *
 * $handler = &new myHandler;
 *
 * $server->setCallbackObject($handler);
 *
 * $server->start();
 * </code>
 *
 * See Server/Handler.php for a baseclass that you can
 * use to implement new handlers.
 *
 * @version 1.0alpha
 * @author  Stephan Schmidt <schst@php.net>
 */
class Net_Server extends PEAR {
   /**
    * port to listen
    * @access private
    * @var    integer        $port
    */
    var $port = 10000;

   /**
    * domain to bind to
    * @access private
    * @var    string    $domain
    */
    var $domain = "localhost";

   /**
    * all file descriptors are stored here
    * @access private
    * @var    array    $clientFD
    */
    var $clientFD = array();

   /**
    * maximum amount of clients
    * @access private
    * @var    integer    $maxClients
    */
    var $maxClients = -1;

   /**
    * buffer size for socket_read
    * @access private
    * @var    integer    $readBufferSize
    */
    var $readBufferSize = 128;
    
   /**
    * end character for socket_read
    * @access private
    * @var    integer    $readEndCharacter
    */
    var $readEndCharacter = "\n";
    
   /**
    * maximum of backlog in queue
    * @access private
    * @var    integer    $maxQueue
    */
    var $maxQueue = 500;

   /**
    * debug mode
    * @access private
    * @var    boolean    $_debug
    */
    var $_debug = true;
    
   /**
    * debug mode, normally only text is needed, as servers should not be run in a browser
    * @access private
    * @var    string    $_debugMode
    */
    var $_debugMode = "text";

   /**
    * debug destination (filename or stdout)
    * @access private
    * @var    string    $_debugDest
    */
    var $_debugDest = "stdout";

   /**
    * empty array, used for socket_select
    * @access private
    * @var    array    $null
    */
    var $null = array();
    
   /**
    * needed to store client information
    * @access private
    * @var    array    $clientInfo
    */
    var $clientInfo = array();

   /**
    * constructor _MUST_ not be called directly
    *
    * instead please use the Net_Server::create() method
    * that can be called statically and will return a server
    * of the specified type
    *
    * @access   private
    * @param    string   $domain      domain to bind to
    * @param    integer  $port        port to listen to
    */
    function    Net_Server($domain = "localhost", $port = 10000)
    {
        $this->PEAR();
        
        $this->domain = $domain;
        $this->port   = $port;

        // this is only needed, when server is not run in CLI
        set_time_limit(0);
    }

   /**
    * destructor
    *
    * @access   private
    */
    function    _Net_Server()
    {
        $this->shutdown();
    }

   /**
    * create a new server
    *
    * Currently two types of servers are supported:
    * - 'sequential', creates a server where one process handles all request from all clients sequentially
    * - 'fork', creates a server where a new process is forked for each client that connects to the server. This only works on *NIX
    *
    * @access public
    * @static
    * @param  string    $type   type of the server
    * @param  string    $host   hostname
    * @param  integer   $port   port
    */
    function &create($type, $host, $port)
    {
        if (!function_exists('socket_create')) {
            return $this->raiseError('Sockets extension not available.');
        }

        $type       =   ucfirst(strtolower($type));
        $driverFile =   'Net/Server/Drivers/' . $type . '.php';
        $className  =   'Net_Server_' . $type;
        
        if (!file_exists($driverFile)) {
            return PEAR::raiseError('Unknown server type');
        }
        
        include_once $driverFile;
        
        if (!class_exists($className)) {
            return PEAR::raiseError('Driver file is corrupt.');
        }

        $server = &new $className($host, $port);
        return $server;
    }
    
   /**
    * set debug mode
    *
    * @access   public
    * @param    mixed    $debug   [text|htmlfalse]
    * @param    string   $dest    destination of debug message (stdout to output or filename if log should be written)
    */
    function    setDebugMode($debug, $dest = "stdout")
    {
        if ($debug === false) {
            $this->_debug = false;
            return true;
        }
        
        $this->_debug     = true;
        $this->_debugMode = $debug;
        $this->_debugDest = $dest;
    }


   /**
    * read from a socket
    *
    * @access   private
    * @param    integer   $clientId    internal id of the client to read from
    * @return   string    $data        data that was read
    */
    function    readFromSocket($clientId = 0) {
        //    start with empty string
        $data        =    "";
    
        //    read data from socket
        while($buf = socket_read($this->clientFD[$clientId], $this->readBufferSize)) {
            $data    .=    $buf;

            $endString    =    substr($buf, - strlen($this->readEndCharacter));
            if ($endString == $this->readEndCharacter) {
                break;
            }
            if ($buf == NULL) {
                break;
            }
        }

        if ($buf === false) {
            $this->_sendDebugMessage("Could not read from client ".$clientId." (".$this->getLastSocketError($this->clientFD[$clientId]).").");
        }

        return $data;
    }

   /**
    * send a debug message
    *
    * @access private
    * @param  string    $msg    message to debug
    */
    function    _sendDebugMessage($msg) {
        if (!$this->_debug) {
            return false;
        }

        $msg    =    date("Y-m-d H:i:s", time()) . " " . $msg;    
            
        switch($this->_debugMode) {
            case    "text":
                $msg    =    $msg."\n";
                break;
            case    "html":
                $msg    =    htmlspecialchars($msg) . "<br />\n";
                break;
        }

        if ($this->_debugDest == "stdout" || empty($this->_debugDest)) {
            echo    $msg;
            flush();
            return true;
        }
        
        error_log($msg, 3, $this->_debugDest);
        return true;
    }

   /**
    * register a callback object, that is used to handle all events
    *
    * @access public
    * @param  object    $object     callback object
    */
    function setCallbackObject(&$object)
    {
        $this->callbackObj = &$object;
        if (method_exists($this->callbackObj,'setServerReference')) {
            $this->callbackObj->setServerReference($this);
        }
    }
    
   /**
    * return string for last socket error
    *
    * @access   public
    * @return string    $error    last error
    */
    function    getLastSocketError(&$fd) {
        if(!is_resource($fd)) {
            return "";
        }
        $lastError    =    socket_last_error($fd);
        return "Msg: " . socket_strerror($lastError) . " / Code: ".$lastError;
    }
}
?>