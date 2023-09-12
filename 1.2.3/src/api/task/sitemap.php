<?php

    $FRONTEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $DIR = __DIR__;
    $ROOT = str_replace("/api/task", "", $DIR);
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    $SITEMAP_FILE = $ROOT_APP."/generator/sitemap/data/generator.conf";

    $ARRAY = [
        "xs_inc_skip" => "\.(xml|doc|docx|eps|ps|txt|rtf|ppt|xls|rss|pdf)",
        "xs_exc_skip" => "\.(divx|flv|zip|m4a|m4v|rar|tar|bz2|tgz|exe|gif|tif|jpg|jpeg|png|class|jar|mpeg|mpg|mp3|wav|mp4|avi|wmv|gz|mov|mid|ra|ram|css|ico)",
        "xs_proto_skip" => "(\#|tel:|mms:|skype:|mailto:|javascript:|ftp:|news:|aim:)",
        "xs_exec_time" => "9000",
        "xs_initurl" => $PATH->site,
        "xs_freq" => "daily",
        "xs_lastmod" => "1",
        "xs_priority" => "1.0",
        "xs_descpriority" => "0.8",
        "xs_autopriority" => "1",
        "xs_smname" => "$ROOT/shared/sitemap/sitemap.xml",
        "xs_smurl" => "$PATH->site/shared/sitemap/sitemap.xml",
        "xs_webinfo" => "1",
        "xs_imgfilename" => "sitemap_images.xml",
        "xs_mobilefilename" => "sitemap_mobile.xml",
        "xs_videofilename" => "sitemap_video.xml",
        "xs_video_extract" => "1",
        "xs_newsfilename" => "sitemap_news.xml",
        "xs_newstitle" => "Your Title",
        "xs_newsage" => "2",
        "xs_newslang" => "en",
        "xs_rssfilename" => "feed_rss.xml",
        "xs_rsstitle" => "My Feed",
        "xs_rssage" => "30",
        "xs_gping" => "1",
        "xs_makehtml" => "1",
        "xs_maketxt" => "1",
        "xs_savestate_time" => "30",
        "xs_sm_size" => "40000",
        "xs_sm_filesize" => "10",
        "xs_usecurl" => "1",
        "xs_robotstxt" => "1",
        "xs_dumptype" => "serialize",
        "xs_cleanpar" => "PHPSESSID|sid|osCsid",
        "xs_chlogorder" => "asc",
        "xs_exclude_check" => "1",
        "xs_dateformat" => "Y, F j",
        "xs_allow_httpcode" => "200",
        "xs_purgelogs" => "30",
        "xs_htmlpart" => "1000",
        "xs_max_depth" => "150",
        "xs_memlimit" => "256",
        "xs_maxref" => "2",
        "xs_progupdate" => "20",
        "xs_checkver" => "1",
        "xs_ext_skip" => "/embed|/share|/like\.php",
        "xs_ext_max" => "1000",
        "xs_chlog_list_max" => "1000",
        "xs_http_language" => "",
        "xs_ror_max" => "50000",
        "xs_newsinfo_max" => "20000",
        "xs_rssinfo_max" => "20000",
        "xs_ref_list_max" => "20000",
        "xs_htmlname" => "$ROOT/shared/sitemap/sitemap.html",
        "xs_notconfigured" => "0",
        "xs_lastmodtime" => date("Y-m-d H:i:s"),
        "xs_max_pages" => "",
        "xs_delay_req" => "",
        "xs_delay_ms" => "",
        "xs_yping" => "",
        "xs_excl_urls" => "",
        "xs_incl_urls" => "",
        "xs_noincl_urls" => "",
        "xs_incl_only" => "",
        "xs_parse_only" => "",
        "xs_ind_attr" => "",
        "xs_email" => "",
        "xs_chlog" => "0",
        "xs_extlinks" => "1",
        "xs_extlinks_excl" => "",
        "xs_makeror" => "0",
        "xs_htmlsort" => "0",
        "xs_htmlstruct" => "0",
        "xs_imginfo" => "1",
        "xs_imgincmask" => "",
        "xs_img_allow_domains" => "",
        "xs_rssinfo" => "1",
        "xs_rssincmask" => "",
        "xs_makemob" => "1",
        "xs_mobileincmask" => "",
        "xs_autoresume" => "",
        "xs_ref_list_store" => "",
        "xs_no_cookies" => "0",
        "xs_compress" => "0",
        "xs_memsave" => "0",
        "xs_ipconnection" => "",
        "xs_angroups" => "",
        "xs_moreurls" => "",
        "xs_allow_subdomains" => "1",
        "xs_canonical" => "1",
        "xs_disable_xsl" => "0",
        "xs_nobrand" => "1",
        "xs_hreflang" => "1",
        "xs_alt_lang" => "",
        "xs_utf8" => "1",
        "xs_inc_ajax" => "0",
        "xs_lastmod_notparsed" => "0",
        "xs_debug" => "0"
    ];

    $XML = "<xmlsitemaps_settings>\n";

    foreach ($ARRAY as $name => $value) { $XML .= '   <option name="'.$name.'">'.$value.'</option>'."\n";}

    $XML .= "</xmlsitemaps_settings>";

    $file = fopen($SITEMAP_FILE, "w");
    fwrite($file, $XML);
    fclose($file);

    require_once $ROOT_APP."/generator/sitemap/runcrawl.php";

?>