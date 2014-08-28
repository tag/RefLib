<?php

namespace RefLib;

/**
* A single citation reference
* @author Tom Gregory <tom@alt-tag.com>
* @see https://github.com/tag/RefLib
* @see https://github.com/hash-bang/RefLib
*/

class Reference implements \ArrayAccess
{
    /**
    * Each refernce has the following keys:
    */
    protected $keys = array(
        'access-date',   // - String (optional) - Unix epoc
        'authors',       // Array of authors
        'address',       // String (optional)
        'contact-name',  // String (optional)
        'contact-email', // String (optional)
        'title',         // String
        'title-secondary', // String (optional)
        'title-short',   // String (optional)
        'periodical-title', // - String (optional)
        'pages',         // String (optional)
        'volume',        // String (optional)
        'number',        // String (optional) - Issue #
        'section',       // String (optional)
        'year',          // String (optional) - Four digit year number e.g. '2014'
        'date',          // String (optional) - Unix epoc
        'abstract',      // String (optional)
        'urls',          // Array
        'notes',         // String (optional)
        'research-notes', // - String (optional)
        'isbn',          // String (optional)
        'label',         // String (optional)
        'caption',       // String (optional)
        'language',      // String (optional)
//TODO: Fix
//        'custom', //{1..7} - String (optional)
//TODO: Keywords
    );

    /**
     * Will have more keys; just initialized with these two.
     **/
    protected $data = [
        'title'   => '',
        'authors' => []
    ];

    /**
     * @return boolean
     **/
    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    /**
     * @return mixed
     **/
    public function &offsetGet($offset)
    {
        return $this->data[$offset];
    }

    /**
     * @return void
     **/
    public function offsetSet($offset, $value)
    {
        //TODO: Filter $offset
        $this->data[$offset] = $value;
    }

    /**
     * @return void
     **/
    public function offsetUnset($offset)
    {
        //TODO: Filter; don't permit title, authors. Reset instead.
        unset($this->data[$offset]);
    }
}