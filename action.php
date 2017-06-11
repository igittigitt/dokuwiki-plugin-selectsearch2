<?php
if(!defined('DOKU_INC')) die();

class action_plugin_selectsearch extends DokuWiki_Action_Plugin {

    /**
     * Register its handlers with the DokuWiki's event controller
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('DOKUWIKI_STARTED', 'AFTER',  $this, '_fixquery');
        $controller->register_hook('TPL_ACT_RENDER', 'BEFORE',  $this, '_tpl_content_core');
    }

    /**
     * Put namespace into search
     */
    function _fixquery(Doku_Event $event, $param) {
        global $QUERY;
        global $ACT;

        if($ACT != 'search'){
            $QUERY = '';
            return;
        }

        if(trim($_REQUEST['namespace'])){
            #$QUERY .= ' @'.trim($_REQUEST['namespace']);
            $QUERY .= ' '.trim($_REQUEST['namespace']);
        }
    }

    function _tpl_content_core(Doku_Event $event, $param)
    {
	global $ACT;
	if ($ACT == 'search') {
	    $this->html_search();
	    $event->preventDefault();
	}
    }

    /**
     * Run a search and display the result (superseed Dokuwikis own html_search)
     */
    function html_search()
    {
	global $QUERY;
	global $ID;
	global $lang;
	global $conf;

	//show progressbar
	print '<div id="dw__loading">'.NL;
	print '<script type="text/javascript">/*<![CDATA[*/'.NL;
	print 'showLoadBar();'.NL;
	print '/*!]]>*/</script>'.NL;
	print '</div>'.NL;
	flush();

	//do fulltext search
	$time_start = microtime(true);
	$data = ft_pageSearch($QUERY,$regex);
	$searchtime = microtime(true) - $time_start;
	$hitcount = count($data);
	if ($hitcount) {
	    print '<div style="margin-bottom: 0.5em;">' . $hitcount . ' Ergebnisse (' . sprintf("%.2f",$searchtime) . ' Sekunden)' . '</div>';
	    print '<style type="text/css" scoped>
		a.wikilink1 { font-size:1.3em; }
		.dokuwiki dl.search_results dt { margin-bottom: 0px; }
		div.searchresult_namespace { font-size: 0.7em; margin-bottom: .4em; }
	    </style>';
	    print '<dl class="search_results">';
	    $num = 1;
	    $hits_per_page = 10;
	    $num_pages = ceil($hitcount / $hits_per_page);
	    $offset = 0;
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
		$linkname = p_get_first_heading($conf['start']);
		for($i = 0; $i < $count - 1; $i++) {
		    $part .= $parts[$i].':';
		    $page = $part;
		    if($page == $conf['start']) {
			continue; // Skip startpage
		    }
		    $linkname .= $sep;
		    $linkname .= p_get_first_heading($page . $conf['start']);
		}
		print html_wikilink(getNS($id).':'.$conf['start'],$linkname);
		print "</div>";

		// preview matching part of page
		if ($cnt !== 0) {
		    print '<dd>'.ft_snippet($id,$regex).'</dd>';
		}
		flush();
	    }
	    print '</dl>';

	    // show pager
	    if ($num_pages > 1) {
		print '<div>';
		for ($i = 1; $i <= $num_pages; $i++) {
			print '<span style="margin-right: 1em;"><a href="">';
			if ($i == 1) {
			    print '<b>' . $i . '</b>';
			} else {
			    print $i;
			}
			print '</a></span>';
		}
		print '</div>';
	    }

	}else{
	    print '<div class="nothing">'.'Die Suche nach '.hsc($QUERY).' ergab keinen Treffer.'.'</div>';
	}

	//hide progressbar
	print '<script type="text/javascript">/*<![CDATA[*/'.NL;
	print 'hideLoadBar("dw__loading");'.NL;
	print '/*!]]>*/</script>'.NL;
	flush();
    }

    function tpl_searchform() {

        global $QUERY;
	global $ID;

        $searchnamespaces = explode(PHP_EOL,$this->getConf('searchnamespaces'));
        foreach ($searchnamespaces as $ns) {
            list($namespace,$displayname) = explode("|",$ns);
            trim($namespace);
            trim($displayname);
	    if ($namespace == '@NS@') {
		$namespace = '@'.getNS($ID);
		$pt = p_get_first_heading($ID);
		if (strlen($pt) > 15) {
		    $pt = substr($pt,0,15) . '...';
		}
		$displayname = 'Nur in "'.$pt.'"';
	    }
            $namespaces[$namespace] = $displayname;
        }

        $cur_val = isset($_REQUEST['namespace']) ? $_REQUEST['namespace'] : '';

        echo '<form id="dw__search" class="search" method="post" accept-charset="utf-8" action="">';
        echo '<div class="no">';
        echo '<select class="selectsearch_namespace" name="namespace">';
        foreach ($namespaces as $ns => $displayname){
            echo '<option value="'.hsc($ns).'"'.($cur_val === $ns ? ' selected="selected"' : '').'>'.hsc($displayname).'</option>';
        }
        echo '</select>';
        echo '<input type="hidden" name="do" value="search" />';
        echo '<input type="hidden" id="qsearch__in"/>';
        echo '<input type="hidden" name="id" value="'.$ID.'"/>';
        #echo '<input class="edit" id="selectsearch__input" type="text" name="id" autocomplete="off" title="[F]" value="'.hsc(preg_replace('/ ?@\S+/','',$QUERY)).'" accesskey="f" />';
        echo '<input class="edit" id="selectsearch__input" type="text" name="query" autocomplete="off" title="[F]" value="'.hsc(preg_replace('/ ?[\^@]\S+/','',$QUERY)).'" accesskey="f" />';
        echo '<input class="button" type="submit" title="Search" value="Search">';
        echo '<div id="qsearch__out" class="ajax_qsearch JSpopup" style="display: none;"></div>';
        echo '</div>';
        echo '</form>';
    }

}
