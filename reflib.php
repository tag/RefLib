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
    * Each refernce has the following keys:
    *   * access-date - String (optional) - Unix epoc
    *   * authors - Array of authors
    *   * address - String (optional)
    *   * contact-name - String (optional)
    *   * contact-email - String (optional)
    *   * title - String
    *   * title-secondary - String (optional)
    *   * title-short - String (optional)
    *   * periodical-title - String (optional)
    *   * pages - String (optional)
    *   * volume - String (optional)
    *   * number - String (optional) - Issue #
    *   * section - String (optional)
    *   * year - String (optional) - Four digit year number e.g. '2014'
    *   * date - String (optional) - Unix epoc
    *   * abstract - String (optional)
    *   * urls - Array
    *   * notes - String (optional)
    *   * research-notes - String (optional)
    *   * isbn - String (optional)
    *   * label - String (optional)
    *   * caption - String (optional)
    *   * language - String (optional)
    *   * custom{1..7} - String (optional)
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
    * The currently active driver for this instance
    * @var string
    */
    public $driver = null;

    /**
    * Whenever a fix is applied (See $applyFix*) any data that gets rewritten should be stored in $ref[]['RAW']
    * @type bool
    */
    public $fixesBackup = false;

    /**
    * Enables the auto-fixing of reference.pages to be absolute
    * Some journals mangle the page references for certain references, this attempts to fix that during import
    * e.g. pp520-34 becomes 520-534
    * @see FixPages()
    * @var bool
    */
    public $applyFixPages = true;

    /**
    * What functions should be transparently mapped onto the driver
    * All keys should be lower case with the values as the function name to pass onto
    * @var array
    */
    protected $_driverMaps = array(
        'getfilename' => 'GetFilename',
        'getcontents' => 'GetContents',
        'setcontents' => 'SetContents',
        'escape' => 'Escape',
    );

    /**
    * Magic methods - these usually map onto the driver
    */
    public function __call($method, $params)
    {
        if (isset($this->_driverMaps[strtolower($method)])) {
            return call_user_func_array(array($this->driver, $method), $params);
        }
        trigger_error("Invalid method: $method");
    }
    // }}}

    // Driver functions {{{
    /**
    * Load a specific driver
    * @param string $driver The name of the driver to load. This should correspond to the driver name in drivers/*.php
    * @return bool TRUE if the driver is valid OR already loaded, FALSE if the driver cannot be loaded
    */
    public function loadDriver($driver)
    {
        $driver = strtolower($driver);
        if ($driver == $this->driver) { // Already loaded this driver
            return true;
        }
        if (!file_exists($file = dirname(__FILE__) . "/drivers/$driver.php")) {
            return;
        }
        require_once($file);
        $driverName = "\RefLib\RefLib_" . ucfirst($driver);
        $this->driver = new $driverName();
        $this->driver->parent = $this;
        return true;
    }

    /**
    * Returns an array of known drivers
    */
    public function getDrivers()
    {
        return array(
            'endnotexml' => 'EndNote XML',
            'ris' => 'RIS',
            'csv' => 'CSV - Excel Export',
        );
    }

    /**
    * Tries to identify the correct driver to use based on an array of data
    * @param array $types,... An array of known data about the file. 
    *              Usually this is the file extension (if any) and mime type
    * @return string Either a suitable driver name or boolean false
    */
    public function identifyDriver()
    {
        $types = func_get_args();
        foreach ($types as $type) {
            switch ($type) {
                case 'xml':
                case 'text/xml':
                    return 'endnotexml';
                case 'ris':
                    return 'ris';
                case 'csv':
                case 'text/csv':
                    return 'csv';
                default: // General file
                    if (is_file($type)) {
                        if (function_exists('mime_content_type')
                            && $mime = mime_content_type($type) ) {
                            if ($type == 'text/csv') {
                                return 'csv';
                            }
                        }
                        // Still no idea - try internal tests
                        $preview = $this->slurpPeek($type);
                        if (preg_match('/^TY  - /ms', $preview)) {
                            return 'ris';
                        }
                    }
            }
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
        $fh = fopen($file, 'r');

        $i = 0;
        $out = '';
        while ($i < $lines && $line = fgets($fh)) {
            $out .= $line;
        }

        fclose($fh);
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
    * @param $ref array The array to add to the stack
    */
    public function add($ref)
    {
        // Expand singular -> plurals
        foreach (array(
            'author' => 'authors',
            'url' => 'urls',
        ) as $single => $plural) {
            if (isset($ref[$single])) {
                $ref[$plural] = array($ref[$single]);
                unset($ref[$single]);
            }
        }

        if (isset($ref['date'])) {
            $ref['date'] = $this->toEpoc($ref['date']);
        }

        $this->refs[] = $ref;
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
            if (! $driver = $this->IdentifyDriver($filename)) {
                trigger_error("Unknown reference driver to use with filename '$filename'");
            } else {
                $this->loadDriver($driver);
            }
        } else {
            $filename = $this->driver->GetFilename();
        }
        header('Content-type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $this->driver->GetContents();
    }

    /**
    * Set the BLOB contents of the incomming citation library from a file
    * This function will also attempt to identify the correct driver to use (via IdentifyDriver())
    * @param string $filename The actual file path to load
    * @param string $mime Optional mime type informaton if the filename doesnt provide 
    *        anything helpful (such as it originating from $_FILE)
    */
    public function importFile($filename, $mime = null)
    {
        if ($driver = $this->IdentifyDriver(pathinfo($filename, PATHINFO_EXTENSION), $mime, $filename)) {
            $this->loadDriver($driver);
            $this->driver->SetContents(file_get_contents($filename));
        } else {
            trigger_error("Unknown driver type for filename '$filename'");
        }
    }
    // }}}

    // Fixes {{{
    /**
    * Apply all enabled features
    * This is really just one big switch that enables the $this->Fix* methods
    * @param array $ref The reference to fix
    * @return array $ref The now fixed reference
    * @todo Move to parent class of drivers; utilize in all drivers
    */
    public function applyFixes($ref)
    {
        if ($this->applyFixPages) {
            $ref = $this->fixPages($ref);
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
    public function fixPages($ref)
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
            list($junk, $begin, $end) = $matches;
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
            if ($this->fixesBackup) {
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
    * @param array|null $ref Optional additional reference information. This is used when the date needs more context e.g. 'Aug'
    * @return int An epoc value
    */
    public function toEpoc($date, $ref = null)
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
    * Attempts to understand the divider between author fields and returns back the field in '$author1$outseperator$author2' format
    * @param string $authors The incomming author field to process
    * @param array|string $seperators An array of seperators to try, if none specified a series of internal seperators is used, if this is a string only that seperator will be used and no other
    * @param string $outseperator The output seperator to use
    * @return string The supporte author field
    */
    public function joinAuthors($authors, $seperators = null, $outseperator = ' AND ')
    {
        if (!$seperators) {
            $seperators = array(', ', '; ', ' AND ');
        }

        foreach ((array) $seperators as $seperator) {
            $bits = explode($seperator, $authors);
            if (count($bits) > 1) {
                return implode($outseperator, $bits);
            }
        }

        return $authors;
    }
    // }}}


    /**
    * --- BACKWARD COMPATABILITY ALIASES ---
    **/

    // @codingStandardsIgnoreStart

    // @codingStandardsIgnoreEnd
}
