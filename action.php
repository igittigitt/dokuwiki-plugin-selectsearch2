<?php
if(!defined('DOKU_INC')) die();

class action_plugin_selectsearch extends DokuWiki_Action_Plugin {

    /**
     * Register its handlers with the DokuWiki's event controller
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('TPL_ACT_RENDER', 'BEFORE',  $this, '_tpl_content_core');
        $controller->register_hook('TPL_CONTENT_DISPLAY', 'BEFORE',  $this, '_tpl_replace_searchform');
        $controller->register_hook('TEMPLATE_PAGETOOLS_DISPLAY', 'BEFORE',  $this, '_tpl_pagetools_display');
    }

    /**
     * Replace pagetools while showing search results
     */
    function _tpl_pagetools_display(Doku_Event $event, $param)
    {
	global $ID;
	global $ACT;

	if ($ACT != 'search') {
	    return;
	}

	$caption = 'Zur Seitenansicht zurückkehren';
	$link = tpl_link(wl($ID, array('do'=>'show', 'rev'=>0)), "<span>$caption</span>", 'class="action show" accesskey="v" rel="nofollow" title="'.hsc($caption).' [V]"', true);
	$event->data = array('view'=>'main', 'items'=>array('show'=>'<li>'.$link.'</li>'));

	$event->stopPropagation();
    }

    /**
     * Append new searchform HTML to page and use jQuery to replace default searchform with its content
     * THIS IS A HACK
     */
    function _tpl_replace_searchform(Doku_Event $event, $param)
    {
	// first, append new searchform element ('dw_selectsearch') to current page
	$event->data .= $this->tpl_searchform();
	// next, add JavaScript to replace searchform
	$event->data .= "<script type=\"text/javascript\"> jQuery(\"#dw__search\").remove(); jQuery(\"#dokuwiki__sitetools\").prepend(jQuery(\"#dw__selectsearch\")); </script>";
    }

    /**
     * called by TPL_SEARCHFORM_DISPLAY event
        #$controller->register_hook('TPL_SEARCHFORM_DISPLAY', 'BEFORE',  $this, '_tpl_searchform');
     */
/*
    function _tpl_searchform(Doku_Event $event, $param)
    {
	print $this->tpl_searchform();
	$event->preventDefault();
    }
*/

    /**
     * Intercepted TPL_ACT_RENDER used to do custom search instead of default wiki search
     */
    function _tpl_content_core(Doku_Event $event, $param)
    {
	if ($event->data == 'search') {
	    $this->html_search();
	    $event->preventDefault();
	}
    }

    /**
     * Run a search and display the result (superseed Dokuwikis own html_search)
     */
    function html_search()
    {
	global $ID;
	global $lang;
	global $conf;
	global $INPUT;

	$query = $INPUT->str('selectsearch_query');
	$namespace = $INPUT->str('selectsearch_namespace');
	$cur_page = $INPUT->int('selectsearch_curpage');

	//do fulltext search
	$time_start = microtime(true);

        if($namespace) {
            $query = $query . ' ' .$namespace;
        }
	$data = ft_pageSearch($query, $regex);
	$hitcount = count($data);
	if ($hitcount) {
	    // show number of search results and needed runtime
	    print '<div class="selectsearch_resultpage">';
	    if ($cur_page > 1) {
		print 'Seite '.$cur_page.' von '.$hitcount.' Ergebnissen';
	    } else {
		print $hitcount . ' Ergebnisse';
	    }
	    print ' (<span id="dw_selectsearch_runtime"></span> Sekunden)</div>';

	    // show search result page
	    print '<dl class="search_results">';
	    $num = 1;
	    $hits_per_page = 10; // TODO: make this an option somewhere
	    $num_pages = ceil($hitcount / $hits_per_page);
	    if ($cur_page > 1) {
		$offset = ($hits_per_page * ($cur_page - 1)) -1;
	    } else {
		$offset = 0;
	    }
	    $page = array_slice($data, $offset, $hits_per_page, true);
	    foreach($page as $id => $cnt)
	    {
		// show matching page as link
		print '<dt>';
		print html_wikilink(':'.$id,useHeading('navigation')?null:$id,$regex);
		print '</dt>';

		// show full namespace path as link
		print '<div class="searchresult_namespace">';
		$sep = ' » ';
		$parts = explode(':', $id);
		$count = count($parts);
		$part = '';
		$ns = p_get_first_heading($conf['start']);
		for($i = 0; $i < $count - 1; $i++) {
		    $part .= $parts[$i].':';
		    $page = $part;
		    if($page == $conf['start']) {
			continue; // Skip startpage
		    }
		    $ns .= $sep;
		    $ns .= p_get_first_heading($page . $conf['start']);
		}
		print $ns;
		// show full namespace path as link
		//print html_wikilink(getNS($id).':'.$conf['start'],$linkname);
		print '&nbsp;<a class="wikilink1" href="' . wl(getNS($id).':'.$conf['start'], array('id'=>$id,'do'=>'search','selectsearch_query'=>$INPUT->str('selectsearch_query'),'selectsearch_namespace'=>'@'.getNS($id))) . '">Diesen Bereich durchsuchen...</a>';
		print "</div>";

		// preview matching part of page
		$snipsep_in = '»…';
		$snipsep_out = '…«';
		if ($cnt !== 0) {
		    $context = ft_snippet($id,$regex);
		    $context = str_replace('...', ' '.$snipsep_out.'<br/>'.$snipsep_in.' ', $context);
		    print '<dd>'.$snipsep_in.' '.$context.' '.$snipsep_out.'</dd>';
		}
	    }
	    print '</dl>';

	    // show pagination
	    if ($num_pages > 1)
	    {
		print '<script type="text/javascript">
		function showPage(page) {
		    jQuery("#selectsearch__curpage").val(page);
		    jQuery("#dw__selectsearch").submit();
		}
		</script>';

		print NL.'<div class="pagination">';
		if ($cur_page > 1) {
		    print '<a href="#" onclick="showPage('.($cur_page-1).'); return false;">&lt; Zurück</a>';
		} else {
		    #print '<a href="#" class="disabled" onclick="return false">&lt; Zurück</a>';
		    print '<span class="disabled">&lt; Zurück</span>';
		}
		for ($i = 1; $i <= $num_pages; $i++)
		{
			if ($i == $cur_page) {
			    print '<a href="#" class="active" onclick="showPage(' .$i .'); return false;">' . $i . '</a>';
			} else {
			    print '<a href="#" onclick="showPage(' .$i .'); return false;">' . $i . '</a>';
			}
		}
		if ($cur_page < $num_pages) {
		    print '<a href="#" onclick="showPage('.($cur_page+1).'); return false;">Weiter &gt;</a>';
		} else {
		    #print '<a href="#" class="disabled" onclick="return false">Weiter &gt;</a>';
		    print '<span class="disabled">Weiter &gt;</span>';
		}
		print '</div>'.NL;
	    }

	    // add statistics
	    $runtime = microtime(true) - $time_start;
	    $seconds = str_replace('.',',',sprintf("%.2f",$runtime));
	    print '<script type="text/javascript">/*<![CDATA[*/'.NL;
	    print "jQuery('#dw_selectsearch_runtime').html('" . $seconds . "');";
	    print '/*!]]>*/</script>'.NL;
	}
	else {
	    print '<div class="nothing">'.'Die Suche nach <b>"'.hsc($query).'"</b> ergab keinen Treffer.'.'</div>';
	}
    }


    /**
     * Return HTML of custom searchform with namespace selector
     */
    function tpl_searchform()
    {
	global $ID;
	global $INPUT;
	global $conf;

	$query = $INPUT->str('selectsearch_query');
	$namespace = $INPUT->str('selectsearch_namespace');

	// build namespace dropdown (optional)
	$namespaces = array();
	$nslist = explode(PHP_EOL, trim($this->getConf('searchnamespaces')));
	if ($nslist)
	{
	    foreach ($nslist as $ns)
	    {
		list($ns, $text) = explode("|", $ns);
		$ns = trim($ns);
		$text = trim($text);

		// replace macro @NS@ with current namespace
		if ($ns == '@NS@')
		{
		    if ($ID == $conf['start']) {
			continue;
		    }
		    $ns_start = '@' . getNS($ID);
		    $ns = getNS($ID);
		    if ($ns) {
			$ns = '@' . $ns;
		    }
		    $pt = p_get_first_heading($ns . ':' . $conf['start']);
		    if (strlen($pt) > 15) {
			$pt = substr($pt,0,15) . '...'; # shorten displaytext
		    }
		    $text = str_replace('@TITLE@', $pt, $text);
		}
		$namespaces[$ns] = $text;
	    }
	}

        #$html = '<form id="dw__selectsearch" class="search" method="post" accept-charset="utf-8" action="">';
        $html = '<form id="dw__selectsearch" class="search" method="get" accept-charset="utf-8" action="">';
        $html .= '<div class="no">';
        $html .= '<input type="hidden" name="do" value="search" />';
        $html .= '<input type="hidden" name="id" value="'.$ID.'" />';
        $html .= '<input type="hidden" id="selectsearch__curpage" name="selectsearch_curpage" value="1" />';
	if ($namespaces)
	{
	    $html .= '<select class="selectsearch_namespace" name="selectsearch_namespace">';
	    foreach ($namespaces as $ns => $text){
		$html .= '<option value="' . hsc($ns) . '"';
		if ($ns == $namespace) {
		    $html .= ' selected="selected"';
		}
		$html .= '>' . hsc($text) . '</option>';
	    }
	    $html .= '</select>';
	}
        $html .= '<input type="hidden" id="qsearch__in"/>';
	$html .= '<input class="edit" id="selectsearch__input" type="text" name="selectsearch_query" autocomplete="off" title="[F]" value="'.hsc(preg_replace('/ ?[\^@]\S+/','',$query)).'" accesskey="f" />';
        $html .= '<input class="button" type="submit" title="Search" value="Search">';
#        $html .= '<div id="qsearch__out" class="ajax_qsearch JSpopup" style="display: none;"></div>';
        $html .= '</div>';
        $html .= '</form>';
	return $html;
    }
}
