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
 * Base class for all handlers
 *
 * @category    Networking
 * @package     Net_Server
 * @author      Stephan Schmidt <schst@php.net>
 */

/**
 * Base class for all handlers
 *
 * @category    Networking
 * @package     Net_Server
 * @author      Stephan Schmidt <schst@php.net>
 */
class Net_Server_Handler {

   /**
    * reference to the server object, used to send data to the client
    * @var object
    */
    var $_server;

   /**
    * set a reference to the server object
    * 
    * This is done automatically when the handler is passed over to the server
    *
    * @access public
    * @param  object    Net_Server_* object
    */
    function setServerReference( &$server )
    {
        $this->_server  =   &$server;
    }

   /**
    * onStart handler
    *
    * This handler is called, when the server starts.
    * Available in:
    * - Net_Server_Sequential
    * - Net_Server_Fork
    *
    * @access public
    */
    function onStart()
    {
    }

   /**
    * onShutdown handler
    *
    * This handler is called, when the server is stopped.
    * Available in:
    * - Net_Server_Sequential
    *
    * @access public
    */
    function onShutdown()
    {
    }

   /**
    * onConnect handler
    *
    * This handler is called, when a new client connects
    * Available in:
    * - Net_Server_Sequential
    * - Net_Server_Fork
    *
    * @access public
    * @param  integer   $clientId   unique id of the client, in Net_Server_Fork, this is always 0
    */
    function onConnect($clientId = 0)
    {
    }

   /**
    * onConnectionRefused handler
    *
    * This handler is called, when a new client tries to connect but is not allowed to
    * Available in:
    * - Net_Server_Sequential
    *
    * @access public
    * @param  integer   $clientId   unique id of the client
    */
    function onConnectionRefused($clientId = 0)
    {
    }

   /**
    * onClose handler
    *
    * This handler is called, when a client disconnects from the server
    * Available in:
    * - Net_Server_Sequential
    * - Net_Server_Fork
    *
    * @access public
    * @param  integer   $clientId   unique id of the client, in Net_Server_Fork, this is always 0
    */
    function onClose($clientId = 0)
    {
    }

   /**
    * onReceiveData handler
    *
    * This handler is called, when a client sends data to the server
    * Available in:
    * - Net_Server_Sequential
    * - Net_Server_Fork
    *
    * @access public
    * @param  integer   $clientId   unique id of the client, in Net_Server_Fork, this is always 0
    * @param  string    $data       data that the client sent
    */
    function onReceiveData($clientId = 0, $data = "")
    {
    }
}
?>