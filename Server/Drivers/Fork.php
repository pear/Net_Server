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

/**
 * Forking server class.
 *
 * This class will fork a new process for each connection.
 * This allows you to build servers, where communication between
 * the clients is no issue.
 *
 * Events that can be handled:
 *   - onStart
 *   - onConnect
 *   - onClose
 *   - onReceiveData
 *
 * @version 0.10 alpha
 * @author  Stephan Schmidt <schst@php.net>
 */
class Net_Server_Fork extends Net_Server
{
   /**
    * flag to indicate whether this is the parent
    * @access private
    * @var    boolean
    */
    var $_isParent = true;

   /**
    * set maximum amount of simultaneous connections
    *
    * this is not possible as each client gets its own
    * process
    *
    * @access   public
    * @param    int    $maxClients
    */
    function setMaxClients($maxClients)
    {
        return  $this->raiseError('Not supported.', NET_SERVER_ERROR_NOT_SUPPORTED);
    }

   /**
    * start the server
    *
    * @access   public
    */
    function start()
    {
        if (!function_exists('pcntl_fork')) {
            return $this->raiseError('Needs pcntl extension to fork processes.', NET_SERVER_ERROR_PCNTL_REQUIRED);
        }
    
        $this->initFD    =    @socket_create(AF_INET, SOCK_STREAM, 0);
        if (!$this->initFD) {
            return $this->raiseError("Could not create socket.");
        }

        //    adress may be reused
        socket_setopt($this->initFD, SOL_SOCKET, SO_REUSEADDR, 1);

        //    bind the socket
        if (!@socket_bind($this->initFD, $this->domain, $this->port)) {
            $error = $this->getLastSocketError($this->initFd);
            @socket_close($this->initFD);
            return $this->raiseError("Could not bind socket to ".$this->domain." on port ".$this->port." (".$error.").");
        }

        //    listen on selected port
        if (!@socket_listen($this->initFD, $this->maxQueue)) {
            $error = $this->getLastSocketError($this->initFd);
            @socket_close($this->initFD);
            return $this->raiseError("Could not listen (".$error.").");
        }

        $this->_sendDebugMessage("Listening on port ".$this->port.". Server started at ".date("H:i:s", time()));

        if (method_exists($this->callbackObj, "onStart")) {
            $this->callbackObj->onStart();
        }

        // Dear children, please do not become zombies
        pcntl_signal(SIGCHLD, SIG_IGN);
        
        // wait for incmoning connections
        while (true)
        {
            // new connection
            if(($fd = socket_accept($this->initFD)))
            {
                $pid = pcntl_fork();
                if($pid == -1) {
                    return  $this->raiseError('Could not fork child process.');
                }
                // This is the child => handle the request
                elseif($pid == 0) {
                    // this is not the parent
                    $this->_isParent = false;
                    // store the new file descriptor
                    $this->clientFD[0] = $fd;

                    $peer_host    =    "";
                    $peer_port    =    "";
                    socket_getpeername($this->clientFD[0], $peer_host, $peer_port);
                    $this->clientInfo[0]    =    array(
                                                        "host"        =>    $peer_host,
                                                        "port"        =>    $peer_port,
                                                        "connectOn"   =>    time()
                                                   );
                    $this->_sendDebugMessage("New connection from ".$peer_host." on port ".$peer_port);

                    if (method_exists($this->callbackObj, "onConnect")) {
                        $this->callbackObj->onConnect($i);
                    }

                    $this->serviceRequest();
                    $this->closeConnection();
                    exit;
                }
                else {
                    // the parent process does not have to do anything
                }
                
            }
        }
    }

   /**
    * service the current request
    *
    *
    *
    */
    function serviceRequest()
    {
        while( true )
        {
            $readFDs = array( $this->clientFD[0] );
    
            //    block and wait for data
            $ready    =    @socket_select($readFDs, $this->null, $this->null, null);
    
            if ($ready === false)
            {
                $this->_sendDebugMessage("socket_select failed.");
                $this->shutdown();
            }
    
            if (in_array($this->clientFD[0], $readFDs))
            {
                $data    =    $this->readFromSocket();
    
                //    empty data => connection was closed
                if (!$data)
                {
                    $this->_sendDebugMessage("Connection closed by peer");
                    $this->closeConnection();
                }
                else
                {
                    $this->_sendDebugMessage("Received ".trim($data)." from ".$i);
    
                    if (method_exists($this->callbackObj, "onReceiveData")) {
                        $this->callbackObj->onReceiveData(0, $data);
                    }
                }
            }
        }
    }
    
   /**
    * check, whether a client is still connected
    *
    * @access   public
    * @param    integer    $id         client id
    * @return   boolean    $connected  true if client is connected, false otherwise
    */
    function isConnected() {
        if (is_resource($this->clientFD[0])) {
            return true;
        }
    }

   /**
    * get current amount of clients
    *
    * not possible with forking
    *
    * @access   public
    * @return PEAR_Error
    */
    function getClients() {
        return $this->raiseError('Not implemented');
    }

   /**
    * send data to a client
    *
    * @access   public
    * @param    string    $data        data to send
    * @param    boolean    $debugData    flag to indicate whether data that is written to socket should also be sent as debug message
    */
    function sendData($data, $debugData = true) {
        // keep it compatible to Net_Server_Sequential
        if (is_string($debugData)) {
            $data = $debugData;
        }
    
        if (!isset($this->clientFD[0]) || $this->clientFD[0] == null) {
            return $this->raiseError("Client does not exist.");
        }

        if ($debugData) {
            $this->_sendDebugMessage("sending: \"" . $data . "\" to: $clientId" );
        }
        if (!@socket_write($this->clientFD[0], $data)) {
            $this->_sendDebugMessage("Could not write '".$data."' client ".$clientId." (".$this->getLastSocketError($this->clientFD[$clientId]).").");
        }
    }

   /**
    * send data to all clients
    *
    * @access   public
    * @param    string    $data        data to send
    * @param    array    $exclude    client ids to exclude
    */
    function broadcastData($data, $exclude = array()) {
        $this->sendData($data);
    }

   /**
    * get current information about a client
    *
    * @access   public
    * @return array    $info        information about the client
    */
    function getClientInfo() {
        if (!isset($this->clientFD[0]) || $this->clientFD[0] == null) {
            return $this->raiseError("Client does not exist.");
        }
        return $this->clientInfo[$clientId];
    }

   /**
    * close the current connection
    *
    * @access   public
    */
    function closeConnection() {
        if (!isset($this->clientFD[0])) {
            return $this->raiseError( "Connection already has been closed." );
        }

        if (method_exists($this->callbackObj, "onClose")) {
            $this->callbackObj->onClose($id);
        }

        $this->_sendDebugMessage("Closed connection from ".$this->clientInfo[0]["host"]." on port ".$this->clientInfo[0]["port"]);

        @socket_close($this->clientFD[0]);
        $this->clientFD[0]    =    null;
        unset($this->clientInfo[0]);
        exit();
    }

   /**
    * shutdown server
    *
    * @access   public
    */
    function shutDown() {
        $this->closeConnection();
        exit;
    }
}
?>