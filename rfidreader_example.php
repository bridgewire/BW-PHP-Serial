<?

/* rfidreader_example.php 
 * Author: Christiana Johnson
 * Copyright 2014
 * License: GPL v2  (this is a widely published license. please read it.
 * 
 * This file is a quick work-up and serves well as an example both of how
 * to use the features newly added to the phpSerial class, and for how to
 * use PHP on a Raspberry Pi to access and make use of the ID-12LA family
 * of RFID card readers. 
 * 
 * Except for php_serial.class.php, and the ID-12LA RFID hardware, this
 * file stands alone. This file, though written in PHP, is not intended
 * to be used via a web server, but should be used on the command-line,
 * like this:  'user@host:~$ php rfidreader_example.php'
 */ 

require_once('php_serial.class.php');

class RFID_BadKey_Exception extends Exception {};

class rfid_key
{
 /*
  * This class defines an interface to RFID cards which are read by devices 
  * such as ID-2LA, ID-12LA, and ID-20LA whose data is encoded according to
  * the ASCII Data Format given in the datasheet which may be found here:
  * http://dlnmh9ip6v2uc.cloudfront.net/datasheets/Sensors/ID/ID-2LA,%20ID-12LA,%20ID-20LA(2013-4-10).pdf
  * or otherwise through google, or Sparkfun.
  * 
  * This  code is made to run on a Raspberry Pi whose UART has been "freed"
  * and put into the service of the RFID reader named above. The details of
  * the serial port configuation can be found in the datasheet also.
  * 
  */

  protected $hexdigits = array();
  protected $withdashes = '';
  protected $rawkey = '';

  public function __toString() { return $this->withdashes; }
  public function get_key()    { return $this->withdashes; }
  public function get_rawkey() { return $this->rawkey; }

  public function __construct( $key_string )
  {
    $this->parse_key( $key_string ); // thows excpetions if parsing fails. when parse_key
                                     // finishes rawkey, withdashes, and hexdigits[] are set
  }

  protected function parse_key( $key )
  {
    if( strlen($key) >= 16 )
    {
      // if the input string "$key" contains more than one key, ignore all but the first.
      $matches = array();
      $pattern = '/\002(([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2}))\r\n\003/i';
      if( preg_match($pattern, $key, $matches ) )
      {
        $this->rawkey = $matches[1];

        $sum = 0;
        $checksum = hexdec( $matches[7] );
        $frmtkey = "";
        for( $i = 2; $i < 7; $i++ )
        {
          $this->hexdigits[] = $matches[$i];
          $sum ^= hexdec( $matches[$i] );
          $frmtkey .= ($i > 2 ? '-' : '').$matches[$i] ;
        }
        $this->hexdigits[] = $matches[7];
        $frmtkey .= '-'.$matches[7];

        if( $sum == $checksum )
          $this->withdashes = $frmtkey;
        else
          throw new RFID_BadKey_Exception("bad checksum for received key: $frmtkey");
      }
      else
        throw new RFID_BadKey_Exception("key doesn't pass basic regex checking. received key: $key");
    }
    else
      throw new RFID_BadKey_Exception("complete 16 bytes expected. received key: $key");
  }
}


function errorhandler ( $errno, $errstr, $errfile, $errline )
{
  throw new Exception( basename($errfile).'['.$errline.'] (errno:'.$errno.') -- '.$errstr);
}

set_error_handler( 'errorhandler' );

$s = new phpSerial();

try {

  if( ! $s->confPort( '/dev/ttyAMA0', 9600 )) throw new Exception("confPort() failed");
  if( ! $s->deviceOpen() )                    throw new Exception("deviceOpen() failed");
  if( ! $s->confBlocking( false ) )           throw new Exception("confBlocking() failed");

} catch( Exception $e ) { print "exception: $e\n"; die; }

$print_after_read = false;
$keystring="";
do {
  $r = array( $s->getFilehandle() );
  $w = null;
  $e = null;

  $ready_count = stream_select( $r, $w, $e, NULL );

  if( $ready_count !== false && $ready_count > 0 )
  {
    $keylen = $s->readPort( $keystring );

    if( $print_after_read )  print "readPort() returned $keylen bytes\n";

    if( strlen( $keystring ) >= 16 )
    {
      try
      {
        $key = new rfid_key( $keystring );
        print "received valid rfid key: $key\n";
        $keystring=""; // consume the string after success
      }
      catch (Exception $e) { print "exception: $e\n"; }

      // just in case parsing the key fails repeated we truncate
      // remove leading, useless data from time to time.
      if( strlen( $keystring ) >= 32 )
        $keystring = substr( $keystring, 16 );
    }
  }
} while( true );

/* vim: set ai et tabstop=2  shiftwidth=2: */
?>
