<?php
/**
 * simple example that implements a talkback.
 *
 * Normally this should be a bit more code and in a separate file
 *
 * @category    Networking
 * @package     Net_Server
 * @subpackage  Examples
 * @author      Stephan Schmidt <schst@php.net>
 */

/**
* server base class
*/
require_once 'Net/Server.php';

/**
* base class for the handler
*/
require_once 'Net/Server/Handler.php';

/**
 * simple example that implements a talkback.
 *
 * Normally this should be a bit more code and in a separate file
 *
 * @category    Networking
 * @package     Net_Server
 * @subpackage  Examples
 * @author      Stephan Schmidt <schst@php.net>
 */
class Net_Server_Handler_Talkback extends Net_Server_Handler
{
   /**
    * If the user sends data, send it back to him
    *
    * @access   public
    * @param    integer $clientId
    * @param    string  $data
    */
    function onReceiveData($clientId = 0, $data = '')
    {
        $this->_server->sendData($clientId, 'You said: ' . $data);
    }

   /**
    * No data have been sent since $numberOfSeconds defined by
    * setIdleTimeout().
    * @access   public
    */
    function onIdle()
    {
        $this->_server->sendData(0, "Please say something!\n");
    }
}

// create a server that forks new processes
$server = &Net_Server::create('sequential', 'localhost', 9090);
// you won't need this in most cases.
$server->setIdleTimeout(10);

if (PEAR::isError($server)) {
    echo $server->getMessage()."\n";
}

$handler = &new Net_Server_Handler_Talkback();

// hand over the object that handles server events
$server->setCallbackObject($handler);
$server->readBufferSize = 1;
$server->readEndCharacter = "\n";
$server->_debug = true;

// start the server
$server->start();
?>