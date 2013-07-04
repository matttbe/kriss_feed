<?php
/**
 * Rss class let you download and parse RSS
 */
class Rss
{
    /**
     * Format xml channel into array
     *
     * @param DOMDocument $channel DOMDocument of the channel feed
     *
     * @return array Array with extracted information channel
     */
    public static function formatChannel($channel)
    {
        $newChannel = array();

        // list of format for each info in order of importance
        $formats = array('title' => array('title'),
                         'description' => array('description', 'subtitle'),
                         'htmlUrl' => array('link', 'id', 'guid'));

        foreach ($formats as $format => $list) {
            $newChannel[$format] = '';
            $len = count($list);
            for ($i = 0; $i < $len; $i++) {
                if ($channel->hasChildNodes()) {
                    $child = $channel->childNodes;
                    for ($j = 0, $lenChannel = $child->length;
                         $j<$lenChannel;
                         $j++) {
                        if (isset($child->item($j)->tagName)
                            && $child->item($j)->tagName == $list[$i]
                        ) {
                            $newChannel[$format]
                                = $child->item($j)->textContent;
                        }
                    }
                }
            }
        }

        return $newChannel;
    }

    /**
     * format items into array
     *
     * @param DOMNodeList $items   DOMNodeList of items in a feed
     * @param array       $formats List of information to extract
     *
     * @return array List of items with information
     */
    public static function formatItems($items, $formats)
    {
        $newItems = array();

        foreach ($items as $item) {
            $tmpItem = array();
            foreach ($formats as $format => $list) {
                $tmpItem[$format] = '';
                $len = count($list);
                for ($i = 0; $i < $len; $i++) {
                    if (is_array($list[$i])) {
                        $tag = $item->getElementsByTagNameNS(
                            $list[$i][0],
                            $list[$i][1]
                        );
                    } else {
                        $tag = $item->getElementsByTagName($list[$i]);
                        // wrong detection : e.g. media:content for content
                        if ($tag->length != 0) {
                            for ($j = $tag->length; --$j >= 0;) {
                                $elt = $tag->item($j);
                                if ($tag->item($j)->tagName != $list[$i]) {
                                    $elt->parentNode->removeChild($elt);
                                }
                            }
                        }
                    }
                    if ($tag->length != 0) {
                        // we find a correspondence for the current format
                        // select first item (item(0)), (may not work)
                        // stop to search for another one
                        if ($format == 'link') {
                            $tmpItem[$format] = '';
                            for ($j = 0; $j < $tag->length; $j++) {
                                if ($tag->item($j)->hasAttribute('rel') && $tag->item($j)->getAttribute('rel') == 'alternate') {
                                    $tmpItem[$format]
                                        = $tag->item($j)->getAttribute('href');
                                    $j = $tag->length;
                                }
                            }
                            if ($tmpItem[$format] == '') {
                                $tmpItem[$format]
                                    = $tag->item(0)->getAttribute('href');
                            }
                        }
                        if (empty($tmpItem[$format])) {
                            $tmpItem[$format] = $tag->item(0)->textContent;
                        }
                        $i = $len;
                    }
                }
            }
            if (!empty($tmpItem['link'])) {
                $hashUrl = MyTool::smallHash($tmpItem['link']);
                $newItems[$hashUrl] = array();
                $newItems[$hashUrl]['title'] = $tmpItem['title'];
                $newItems[$hashUrl]['time']  = strtotime($tmpItem['time'])
                    ? strtotime($tmpItem['time'])
                    : time();
                if (MyTool::isUrl($tmpItem['via'])
                    && $tmpItem['via'] != $tmpItem['link']) {
                    $newItems[$hashUrl]['via'] = $tmpItem['via'];
                } else {
                    $newItems[$hashUrl]['via'] = '';
                }
                $newItems[$hashUrl]['link'] = $tmpItem['link'];
                $newItems[$hashUrl]['author'] = $tmpItem['author'];
                mb_internal_encoding("UTF-8");
                $newItems[$hashUrl]['description'] = mb_substr(
                    strip_tags($tmpItem['description']), 0, 500
                );
                $newItems[$hashUrl]['content'] = $tmpItem['content'];
            }
        }

        return $newItems;
    }

    /**
     * return channel from xmlUrl
     *
     * @param DOMDocument $xml DOMDocument of the feed
     *
     * @return array Array with extracted information channel
     */
    public static function getChannelFromXml($xml)
    {
        $channel = array();

        // find feed type RSS, Atom
        $feed = $xml->getElementsByTagName('channel');
        if ($feed->item(0)) {
            // RSS/rdf:RDF feed
            $channel = $feed->item(0);
        } else {
            $feed = $xml->getElementsByTagName('feed');
            if ($feed->item(0)) {
                // Atom feed
                $channel = $feed->item(0);
            } else {
                // unknown feed
            }
        }

        if (!empty($channel)) {
            $channel = self::formatChannel($channel);
        }

        return $channel;
    }

    /**
     * Add a namespaceURI when format corresponds to a rdf tag.
     *
     * @param array   $formats Array of formats
     * @param DOMNode $feed    DOMNode corresponding to the channel root
     *
     * @return array Array of new formated format with namespaceURI
     */
    public static function formatRDF($formats, $feed)
    {
        foreach ($formats as $format => $list) {
            for ($i = 0, $len = count($list); $i < $len; $i++) {
                $name = explode(':', $list[$i]);
                if (count($name)>1) {
                    $res = $feed->getAttribute('xmlns:'.$name[0]);
                    if (!empty($res)) {
                        $ns = $res;
                    } else {
                        $ns = self::getAttributeNS($feed, $list[$i]);
                    }
                    $formats[$format][$i] = array($ns, $name[1]);
                }
            }
        }

        return $formats;
    }

    /**
     * Search a namespaceURI into tags
     * (used when namespaceURI are not defined in the root tag)
     *
     * @param DOMNode $feed DOMNode to look into
     * @param string  $name String of the namespace to look for
     *
     * @return string The namespaceURI or empty string if not found
     */
    public static function getAttributeNS ($feed, $name)
    {
        $res = '';
        if ($feed->nodeName === $name) {
            $ns = explode(':', $name);
            $res = $feed->getAttribute('xmlns:'.$ns[0]);
        } else {
            if ($feed->hasChildNodes()) {
                foreach ($feed->childNodes as $childNode) {
                    if ($res === '') {
                        $res = self::getAttributeNS($childNode, $name);
                    } else {
                        break;
                    }
                }
            }
        }

        return $res;
    }

    /**
     * Return array of items from xml
     *
     * @param DOMDocument $xml DOMDocument where to extract items
     *
     * @return array Array of items extracted from the DOMDocument
     */
    public static function getItemsFromXml ($xml)
    {
        $items = array();

        // find feed type RSS, Atom
        $feed = $xml->getElementsByTagName('channel');
        if ($feed->item(0)) {
            // RSS/rdf:RDF feed
            $feed = $xml->getElementsByTagName('item');
            $len = $feed->length;
            for ($i = 0; $i < $len; $i++) {
                $items[$i] = $feed->item($i);
            }
            $feed = $xml->getElementsByTagName('rss');
            if (!$feed->item(0)) {
                $feed = $xml->getElementsByTagNameNS(
                    "http://www.w3.org/1999/02/22-rdf-syntax-ns#",
                    'RDF'
                );
            }
        } else {
            $feed = $xml->getElementsByTagName('feed');
            if ($feed->item(0)) {
                // Atom feed
                $feed = $xml->getElementsByTagName('entry');
                $len = $feed->length;
                for ($i = 0; $i < $len; $i++) {
                    $items[$i] = $feed->item($i);
                }
                $feed = $xml->getElementsByTagName('feed');
            }
        }

        // list of format for each info in order of importance
        // WORKAROUND matttbe: some versions of xml have problems to load namespaces... ':' => '_'
        $formats = array(
            'author'      => array('author', 'creator', 'dc_author',
                                   'dc_creator'),
            'content'     => array('content_encoded', 'content', 'description',
                               'summary', 'subtitle'),
            'description' => array('description', 'summary', 'subtitle',
                                   'content', 'content_encoded'),
            'via'        => array('guid', 'id'),
            'link'        => array('feedburner_origLink', 'link', 'guid', 'id'),
            'time'        => array('pubDate', 'updated', 'lastBuildDate',
                                   'published', 'dc_date', 'date', 'created',
                                   'modified'),
            'title'       => array('title'));

        if ($feed->item(0)) {
            $formats = self::formatRDF($formats, $feed->item(0));
        }

        return self::formatItems($items, $formats);
    }

    public static function loadDom($data)
    {
        $error = '';
        set_error_handler(array('MyTool', 'silenceErrors'));
        $dom = new DOMDocument();
        $isValid = $dom->loadXML($data);
        restore_error_handler();
        
        if (!$isValid) {
            $error = self::getError(libxml_get_last_error());
        }

        return array(
            'dom' => $dom,
            'error' => $error
        );
    }

    public static function getError($error)
    {
        $return = '';
        
        if ($error === false) {
            $return = Intl::msg('Unknown XML error');
        } else {
            switch ($error->level) {
            case LIBXML_ERR_WARNING:
                $return .= "Warning XML $error->code: ";
                break;
            case LIBXML_ERR_ERROR:
                $return .= "Error XML $error->code: ";
                break;
            case LIBXML_ERR_FATAL:
                $return .= "Fatal Error XML $error->code: ";
                break;
            }
            $return .= trim($error->message);
        }

        return $return;
    }
}
