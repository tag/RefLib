<?php

namespace RefLib;

/**
* Class to manage a variety of citation reference libraries
* @author Matt Carter <m@ttcarter.com>
* @see https://github.com/hash-bang/RefLib
*/
class RefLib
{
    /**
    * An indexed or hash array of references
    * See Reference class for details on fields.
    *
    * @var array
    */
    public $refs = array();

    /**
    * When using SetXML() this field will be used as the ID to refer to the reference
    * If the ID does not exist for this reference an error will be raised
    * Meta types:
    *       NULL - Use next index offset (i.e. $this->refs will be an indexed array)
    *       rec-number - This usually corresponds to the drivers own ID (for example EndNote's
    *       own record number as a reference - only set this if you need to maintain EndNote's
    *       own record numbering against this libraries indexing), but is often just the number
    *       of the reference in the file
    *
    * @var string|null
    */
    public $refId = null;

    /**
    * The current active/default driver for this instance
    * @var AbstractDriver
    */
    public $defaultDriver = null;

    /**
    * Whenever a fix is applied (See $applyFix*) any data that gets rewritten should be stored in $ref[]['RAW']
    * @type bool
    * @todo this really shouldn't be static, but the methods need to be decoupled from the drivers, so it is for now
    */
    public static $fixesBackup = false;

    /**
    * Enables the auto-fixing of reference.pages to be absolute
    * Some journals mangle the page references for certain references, this attempts to fix that during import
    * e.g. pp520-34 becomes 520-534
    * @see FixPages()
    * @var bool
    * @todo this really shouldn't be static, but the methods need to be decoupled from the drivers, so it is for now
    */
    public static $applyFixPages = true;

    /**
    * What functions should be transparently mapped onto the driver
    * All keys should be lower case with the values as the function name to pass onto
    * @var array
    * @todo  deprecated?
    */
    protected $driverMaps = array(
        'getfilename' => 'getFileName',
//        'getcontents' => 'GetContents',
//        'setcontents' => 'SetContents',
        'escape' => 'Escape',
    );

    /**
     * Key refers to file type.
     */
    protected $registeredDrivers = array(
        'ris'=>['class'=>'RisDriver',        'description'=>'RIS'],
        'xml'=>['class'=>'EndNoteXmlDriver', 'description'=>'EndNote XML'],
        'enw'=>['class'=>'EnwDriver',        'description'=>'EndNote ENW'],
        'csv'=>['class'=>'CsvDriver',        'description'=>'CSV']
    );

    /**
    * Magic methods - these usually map onto the driver
    */
    public function __call($method, $params)
    {
        if (isset($this->_driverMaps[strtolower($method)])) {
            return call_user_func_array(array($this->defaultDriver, $method), $params);
        }
        trigger_error("Invalid method: $method");
    }
    // }}}

    /**
     * @return string
     **/
    public function export()
    {
        if (isset($this->defaultDriver)) {
            return $this->defaultDriver->export($this->refs);
        }
        trigger_error("No active driver");
    }

    // Driver functions {{{
    /**
     * Registers drivers for use with RefLib.
     * @param string Class name of driver being registered
     * @return void
     **/
    public function registerDriver($className)
    {
        //TODO: Test $className typeof Drivers\AbstractDriver
        $type = $className::getExtensions();
        foreach ($type as $val) {
            array_unshift(
                $this->registeredDrivers,
                ['class'=>$className, 'type'=>$val]
            );
        }
    }

    /**
    * Load a specific driver
    * @param AbstractDriver|string $driver The extension or fully qualified name of the driver to load,
    *                       as found in $regsiteredDrivers.
    * @return AbstractDriver|null
    * @deprecated
    */
    public function loadDriver($driver)
    {
        // Deprecated way of diong this, but check anyway.
        // Preferred method is to set $this->defaultDriver directly
        if ($driver instanceof AbstractDriver) {
            $this->defaultDriver = $driver;
            return $this->defaultDriver;
        }

        // If $driver is an extension
        $temp = $this->identifyDriver($driver);

        if ($temp) {
            $this->defaultDriver = $temp;
            return $this->defaultDriver;
        }

        // If $driver if a class name
        if (class_exists($driver) && is_subclass_of($driver, '\RefLib\Drivers\AbstractDriver')) {
            $this->defaultDriver = new $driver();
            return $this->defaultDriver;
        }

        return null;
    }

    /**
    * Returns an array of known drivers
    */
    public function getDrivers()
    {
        return $this->registeredDrivers;
    }

    /**
    * Tries to identify the correct driver to use based on an array of data
    * @param array $types,... An array of known data about the file.
    *              Usually this is the file extension (if any), mime type, and possibly, file name
    * @return AbstractDriver Either a suitable driver instance or null
    */
    public function identifyDriver()
    {
        $types = func_get_args();

        foreach ($types as $type) {
            // If $driver is an extension
            if (isset($this->registeredDrivers[strtolower($type)])) {
                $class = __NAMESPACE__.'\\Drivers\\'.$this->registeredDrivers[strtolower($type)]['class'];
                return new $class();
            }

            if (is_file($type)) {
                return $this->identifyDriverFromFile($type);
            }

            if ('text/' == substr($type, 0, 5) && strlen($type) > 5) {
                return $this->identifyDriver(subtr($type, 5));
            }
        }
        return null;
    }

    /**
    * Tries to identify the correct driver to use based on an array of data
    * @param string $fileName File name
    * @return AbstractDriver Either a suitable driver instance or null
    */
    public function identifyDriverFromFile($fileName)
    {
        if (function_exists('mime_content_type')
            && $mime = mime_content_type($fileName) ) {
            if ($mime == 'text/csv') {
                return $this->identifyDriver('csv');
            }
        }
        // Still no idea - try internal tests
        $preview = $this->slurpPeek($fileName);
        if (preg_match('/^TY  - /ms', $preview)) {
            return $this->identifyDriver('ris');
        }
    }

    /**
    * Examine the first $lines number of lines from a given file
    * This is used to help identify the file type in IdentifyDriver
    * @param string $file The file to open
    * @param int $lines The number of lines to read
    * @return string The content lines requested
    * @access protected
    */
    protected function slurpPeek($file, $lines = 10)
    {
        $head = fopen($file, 'r');

        $i = 0;
        $out = '';
        while ($i < $lines && $line = fgets($head)) {
            $out .= $line;
        }

        fclose($head);
        return $out;
    }
    // }}}

    // Adders / removers {{{
    public function reset()
    {
        $this->refs = array();
        $this->name = 'EndNote.enl';
        $this->escapeExport = true;
        $this->fixPages = true;
        $this->fixesBakup = false;
        $this->refId = null;
    }

    /**
    * Add a reference to the $refs array
    * This function also expands simple strings into arrays (suported: author => authors, url => urls)
    * @param array|Reference A reference or array of References to add to the library
    */
    public function add($refList)
    {
        if (isset($refList['title'])) { //poor detection of whether we received a single ref or an array of refs
            $refList = [$refList];
        }
        // Expand singular -> plurals
        // foreach (array(
        //     'author' => 'authors',
        //     'url' => 'urls',
        // ) as $single => $plural) {
        //     if (isset($ref[$single])) {
        //         $ref[$plural] = array($ref[$single]);
        //         unset($ref[$single]);
        //     }
        // }

        foreach ($refList as $ref) {
            if (isset($ref['date'])) {
                $ref['date'] = RefLib::toEpoc($ref['date']);
            }

            $this->refs[] = $ref;
        }
    }
    // }}}

    // Content getters / setters {{{
    /**
    * Generate an XML file and output it to the browser
    * This will force the user to save the file somewhere to be opened later by EndNote
    * @param string $filename The default filename to save as, if unspecifed the driver
    *         default will be used. The filename will be used with IdentifyDriver() if
    *         $driver is unspecified
    * @param string $driver The driver to use when outputting the file, if this setting
    *        is omitted the $filename will be used to compute the correct driver to use
    * @return blob The raw file contents streamed directly to the browser
    * @deprecated Output should be handled by a driver. HTTP headers should be handled 
    *         by framework, but may be assisted by driver.
    */
    public function downloadContents($filename = null, $driver = null)
    {
        if ($filename && $driver) {
            $this->loadDriver($driver);
        } elseif ($filename) { // $filename but no $driver - identify it from the filename
            if (! $driver = $this->identifyDriver($filename)) {
                trigger_error("Unknown reference driver to use with filename '$filename'");
            } else {
                $this->loadDriver($driver);
            }
        } else {
            $filename = $this->driver->getFileName();
        }
        header('Content-type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $this->driver->GetContents();
    }

    /**
    * Set the BLOB contents of the incomming citation library from a file
    * This function will also attempt to identify the correct driver to use (via IdentifyDriver())
    * @param string $filename The actual file path to load
    * @param string $mime Optional mime type informaton if the filename doesn't provide
    *        anything helpful (such as it originating from $_FILE)
    * @param AbstractClass $driver The driver to use. Attempts to auto-detect if none provided
    */
    public function importFile($filename, $mime = null, AbstractClass $driver = null)
    {
        if (!$driver) {
            $driver = $this->identifyDriver(pathinfo($filename, PATHINFO_EXTENSION), $mime, $filename);
        }

        if ($driver) { //TODO: change to import
            $this->defaultDriver = $driver;
            $this->add($this->defaultDriver->import(file_get_contents($filename)));
            return;
        }

        trigger_error("Unknown driver type for filename '$filename'");
    }
    // }}}

    // Fixes {{{
    /**
    * Apply all enabled features
    * This is really just one big switch that enables the $this->Fix* methods
    * Calls self::applyFixes, which doesn't have the check.
    * @todo // TODO: Move these to AbstractDriver?
    * @param array $ref The reference to fix
    * @return array $ref The now fixed reference
    * @todo Move to parent class of drivers; utilize in all drivers
    */
    public static function applyFixes($ref)
    {
        if (self::$applyFixPages) {
            $ref = self::fixPages($ref);
        }
        return $ref;
    }

    /**
    * Fix reference.pages to be absolute
    * Some journals mangle the page references for certain references
    * NOTE: References beginning/ending with 'S' are left with that prefix as that denotes a section
    * e.g. pp520-34 becomes 520-534
    * @param array $ref The refernce object to fix
    * @return array $ref The fixed reference object
    */
    public static function fixPages($ref)
    {
        if (!isset($ref['pages'])) {// Nothing to do
            return $ref;
        }

        $prefix = '';
        $pages = $ref['pages'];
        if (preg_match('/^s|s$/i', $ref['pages'])) { // Has an 'S' prefix or suffix
            $prefix = 'S';
            $pages = preg_replace('/^s|s$/i', '', $pages);
        }

        if (preg_match('/^([0-9]+)\s*-\s*([0-9]+)$/', $pages, $matches)) { // X-Y
            $begin = $matches[1];
            $end   = $matches[2];

            if ((int) $begin == (int) $end) { // Really just a single page
                $pages = $begin;
            } elseif (strlen($end) < strlen($begin)) { // Relative lengths - e.g. 219-22
                $end = substr($begin, 0, strlen($begin) - strlen($end)) . $end;
                $pages = "$begin-$end";
            } else { // Already absolute range
                $pages = "$begin-$end";
            }
        } elseif (preg_match('/^([0-9]+)$/', $pages)) {
            $pages = (int) $pages;
        }

        $pages = $prefix . $pages;
        if ($ref['pages'] != $pages) { // Actually rewrite 'pages'
            if (self::$fixesBackup) {
                if (!isset($ref['RAW'])) {
                    $ref['RAW'] = array();
                }
                $ref['RAW']['pages'] = $ref['pages'];
            }
            $ref['pages'] = $pages;
        }
        $ref['TEST'] = array();
        return $ref;
    }
    // }}}

    // Helper functions {{{
    /**
    * Converts an incomming string to an epoc value suitable for use later on
    * @param string $date The raw string to be converted to an epoc
    * @param array|null $ref Optional additional reference information. This is used when 
    *        the date needs more context e.g. 'Aug'
    * @return int An epoc value
    */
    public static function toEpoc($date, $ref = null)
    {
        if (preg_match('!^[0-9]{10,}$!', $date)) { // Unix time stamp
            return $date;
        } elseif (preg_match('!^[0-9]{4}$!', $date)) { // Just year
            return strtotime("$date-01-01");
        } elseif (preg_match('!^[0-9]{4}-[0-9]{2}$!', $date)) { // Year + month
            return strtotime("$date-01");
        } elseif ($month = array_search(
            $date,
            $months = array(
                'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
            )
        )) {
            if ($ref && isset($ref['year'])) { // We have a year to glue it to
                return strtotime("{$ref['year']}-{$months[$month]}-01");
            }
            return false; // We have the month but don't know anything else
        }
        return strtotime($date);
    }

    /**
    * Returns the date in big-endian (year first) format
    * If the month or day are '01' they are omitted to form the smallest date string
    * possible e.g. '2014-01-01' =~ '2014'
    * @param int $epoc The epoc to return as a string
    * @param string $seperator The seperator to use
    * @param bool $empty If true blanks are still used when no data is available
    *        (e.g. no specific date or month)
    * @return date A prototype date format
    */
    public function toDate($epoc, $seperator = '-', $empty = false)
    {
        if (!$epoc) {
            return false;
        }

        $day = date('d', $epoc);
        if (date('m', $epoc) == '01' && $day == '01') { // Year only format
            return date('Y', $epoc) . ($empty ? "$seperator$seperator" : '');
        } elseif ($day == '01') { // Month only format
            return date('Y/m', $epoc) . ($empty ? $seperator : '');
        }  // Entire date format
        return date('Y/m/d', $epoc);
    }

    /**
    * --- BACKWARD COMPATABILITY ALIASES ---
    **/

    // @codingStandardsIgnoreStart

    // @codingStandardsIgnoreEnd
}
