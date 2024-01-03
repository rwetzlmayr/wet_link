<?php

$plugin['version'] = '0.10';
$plugin['author'] = 'Robert Wetzlmayr';
$plugin['author_uri'] = 'https://wetzlmayr.com/';
$plugin['description'] = 'Create all kinds of links';

@include_once('zem_tpl.php');

if (0) {
?>
# --- BEGIN PLUGIN HELP ---
@<txp:wet_link>@ creates links. It works as a replacement for txp:permlink, and it can also be used to create direct links to articles, pages, images, article lists, and external web sites.

Some examples:

bq. @<txp:wet_link>foo</txp:wet_link>@
In an article form, works the same as txp:permlink

bq. @<txp:wet_link title="permanent link to '%s'">foo</txp:wet_link>@
Same as txp:permlink, except with a title attribute.  The optional '%s' will be replaced with the article title.

bq. @<txp:wet_link href="25">foo</txp:wet_link>@
Direct link to article #25. If a 'title' attribute is supplied, it works the same as for a permanent link, with '%s' replaced with the article title.

bq. @<txp:wet_link href="foo/"&gt;foo&lt;/txp:wet_link&gt;@
Creates a link relative to the textpattern root.

bq. @<txp:wet_link linkid="15">foo</txp:wet_link>@
Direct link to link #15.  If no 'title' attribute is supplied, the link description field is used.

bq. @<txp:wet_link image="15">foo</txp:wet_link>@
Direct link to image #15.  If no 'title' attribute is supplied, the image caption field is used.

As of version 0.3, it is possible to use wet_link as a self-closing tag in certain circumstances:

bq. @<txp:wet_link href="25" />@
Direct link to article #25.  The article title will be used as the link text.

bq. @<txp:wet_link linkid="15" />@
Direct link to link #15.  The link name field will be used as the link text.

As of version 0.4, it is possible to use wet_link as a link to article lists:

bq. @<txp:wet_link section="about">About Us</txp:wet_link>@
Link to the section named "about". Differing from @<txp:section />@, this tag will include the enclosed text as a link.

bq. @<txp:wet_link category="apples">Apples galore</txp:wet_link>@
Link to the category named "apples".

bq. @<txp:wet_link author="eve">All articles by Eve</txp:wet_link>@
Link to a list of Eve's articles.

All of the above forms support the following attributes:

|_. attribute |_. description |
|*href*|the URL or article ID, as described above.|
|*linkid*|the link ID, as described above.|
|*section*|a section's name to link to.|
|*category*|a category's name to link to.|
|*author*|an author's name to link to.|
|*image*|an image's id to link to.|
|*class*|CSS class name.  Optional.|
|*target*|link target.  Optional.|
|*title*|link title.  Optional.  '%s' may be used in links to article and link IDs, to include the article title or link description.|
|*rel*|link attribute "rel".  Optional.|
|*id*|link atribute "id".  Optional.|
|*name*|link attribute "name".  Optional.|
|*accesskey*|link attribute "accesskey".  Optional.|
|*no_widow*| Suppress widows in titles. Optional.|


Either *href*, *image*, *linkid* or *section*, *author*, and *category* are mutually exclusive.

Largely based upon "zem_link":http://thresholdstate.com/software/3702/zem_link. Thanks, Alex!

# --- END PLUGIN HELP ---

# --- BEGIN PLUGIN WET ---
# --- END PLUGIN WET ---

<?php
}

# --- BEGIN PLUGIN CODE ---
\Txp::get('\Textpattern\Tag\Registry')
    ->register('wet_link');

function wet_link($atts, $anchortext='') {
	global $thisarticle, $s, $img_dir, $prefs;

 	extract(lAtts(array(
		'href' => '',
		'linkid' => '',
		'title' => '%s',
		'section' => '',
		'author' => '',
		'category' => '',
		'image' => '',
		'class' => '',
		'target' => '',
		'rel' => '',
		'id' => '',
		'name' => '',
		'accesskey' => '',
 		'no_widow' => @$prefs['title_no_widow']
	),$atts));

	if (!empty($class)) $class = ' class="'.$class.'"';
	if (!empty($target)) $target = ' target="'.$target.'"';
	if (!empty($rel)) $rel = ' rel="'.$rel.'"';
	if (!empty($id)) $id = ' id="'.$id.'"';
	if (!empty($name)) $name = ' name="'.$name.'"';
	if (!empty($accesskey)) $accesskey = ' accesskey="'.$accesskey.'"';

	if ($linkid) {
		$rs = safe_row('*', 'txp_link', 'id=\''.$linkid.'\'');
		if ($rs) {
			$anchortext = (empty($anchortext) ? $rs['linkname'] : $anchortext);
			$title = sprintf($title, $rs['description']);
			$url = $rs['url'];
		}
	}
	elseif ($section.$author.$category != '') {
		# list mode context
		$url = pagelinkurl(array('s'=>$section, 'c'=>$category, 'author'=>$author));
		$title = sprintf($title, '');
	}
	elseif (!$href && !$image) {
		# Permlink context
		if (function_exists('assert_article')) assert_article();
		$title = sprintf($title, $thisarticle['title']);
		if (empty($anchortext)) $anchortext = $thisarticle['title'];
		$url = permlinkurl($thisarticle);
	}
	elseif (is_numeric($href)) {
		# Link to an article by ID
		$rs = safe_row('Title','textpattern','ID=\''.$href."' and Status in ('4','5') limit 1");
		if ($rs) {
			$title = sprintf($title, $rs['Title']);
			$anchortext = (empty($anchortext) ? $rs['Title'] : $anchortext);
			$url = permlinkurl_id($href);
		}
	}
	elseif (substr($href, 0, 5) == 'http:' || substr($href, 0, 6) == 'https:' || substr($href, 0, 4) == 'ftp:') {
		# External or absolute link
		$url = $href;
		$title = sprintf($title);
	}
	elseif ($image) {
		$image = (int) $image;
		$rs = safe_row('*', 'txp_image', "id = $image limit 1");
		$title = sprintf($title, $rs['caption']);
		$anchortext = (empty($anchortext) ? $rs['name'] : $anchortext);
		$url = hu.$img_dir.'/'.$image.$rs['ext'];
	}
	elseif ($href[0] == '/' || $href[0] == '#') {
		# Internal link absolute or fragment identifier
		$url = $href;
		$title = sprintf($title);
	}
	else {
		# Internal link relative to Textpattern root
		$url = hu.$href;
	}

	$title = escape_title($title);
	$title = ($title ? ' title="'.$title.'"' : '');

	# error reporting
	if (!isset($url)) {
		$url = '#';
		$anchortext = '***'.$anchortext.'***';
		$title = ' title="wet_link cannot find item for atts='.htmlentities(var_export ($atts, TRUE)).'"';
	}

	if (!empty($anchortext)) $anchortext = parse($anchortext);
	if ($no_widow) $anchortext = noWidow($anchortext);
	return "<a href=\"$url\"{$title}{$class}{$target}{$rel}{$id}{$name}{$accesskey}>$anchortext</a>";
}


// compatibility shell
function zem_link($atts, $thing='') {
	return wet_link($atts, $thing);
}

# --- END PLUGIN CODE ---

?>
