<?php  /* Parser RSS "112.ua" for Wordpress | Created by Shevchuk Andrey | https://shevchuk-studio.pw | Skype: andrew.shevchuk2  */
ignore_user_abort(true);
set_time_limit(86400);
error_reporting(0); 

// WordPress   libs
require_once($_SERVER["DOCUMENT_ROOT"].'/wp-load.php' );
require_once($_SERVER["DOCUMENT_ROOT"].'/wp-admin/includes/image.php');
//end

//Config
define('rss_feed', 'https://112.ua/rss/avarii-chp/index.rss');
define('max_news', 80); //Max articles
define('post_autor', 1); //User
define('post_category', 4);// Cat 
define('post_status', 'publish'); //Post status
$resource_url =  parse_url(rss_feed);
$resource_url = ''.$resource_url[scheme].'://'.$resource_url[host].'/';
define('resource_url', $resource_url);
//End


//Manth replace
$month_arr = array('January'=>('янв'), 'February'=>('фев'), 'March'=>('мар'), 'April'=>('апр'), 'May'=>('май'), 'June'=>('июн'), 'July'=>('июл'), 'August'=>('авг'), 'September'=>('сен'), 'October'=>('Окт'), 'November'=>('ноя'), 'December'=>('дек'));
 
// RSS
$xml = parserXML(rss_feed);
//end
 

//Add articles
$parsed_items = 0; 
foreach($xml as $item)
{	
	if(max_news == $parsed_items)
	{   //Stop
		break;
	}
	
	//XML item data
	$title = $item[0][value];
	$link = $item[1][value];
	$category = $item[5][value];
	$date = str_replace(',','', $item[4][value]);
	$date = explode(' ', $date);
	$img_prev = $item[7][attributes][url];
	
	//If not to day
	if(date('Ymd', strtotime($date[1].'-'.array_search(''.str_replace('.','',$date[2]).'', $month_arr).'-'.$date[3])) != date('Ymd'))
	{
		continue;
	}
	
	//Full article
	$full_info_arr = parserHTML($link);
	
	//Image
	$image_url = '';
	if(!empty($full_info_arr["full_news_img"]))
	{	$image_url = $full_info_arr["full_news_img"];
	}
	if(empty($image_url))
	{	$image_url = $img_prev;
	}
	$image_url = explode('?',$image_url);
	$image_url = $image_url[0];
	
	//Add post to Wordpress
	$result = addPostWP($title, $full_info_arr['full_news_text'],post_status,post_author,post_category, $image_url, $full_info_arr[tags]);
	
	//Show
	echo $parsed_items.' - post_id: '.$result[post_id].', attachment_id: '.$result[attachment_id].'<br/>';
	
	$parsed_items++;
}
//end


//Full article
function parserHTML($url)
{	//Get full article page
	$data = file_get_contents($url);
	
	//Preparing the content of the full news
	preg_match_all('/<article class="article-content page-cont">(.*?)<\/article>/si', $data, $full_news_text);
	$full_news_text = explode('<div class="article-content_text">', $full_news_text[0][0]);
	
	//Get tags
	preg_match_all('/<div class="article-tags">(.*?)<\/div>/si', $full_news_text[1], $tags);
	$tags = preg_replace ("!<a.*?href=\"?'?([^ \"'>]+)\"?'?.*?>(.*?)</a>!is", "\\2", $tags[1][0]); 
	$tags = explode('#', $tags);

	
	preg_match_all('/<p>(.*?)<\/p>/si', $full_news_text[1], $content);
	$full_news_text =''; $full_news_img ='';
	foreach($content[0] as $item)
	{	//Links cat
		$item = preg_replace ("!<a.*?href=\"?([^ \"'>]+)\"?'?.*?>(.*?)</a>!is", "\\2", $item); 
		$item = preg_replace ("!<small>(.*?)</small>!is", "\\2", $item); 
		//Img
		preg_match_all('/<meta itemprop="url" content="(.*?)">/si', $item, $img); 
		if($img[1][0]!=''){$full_news_img = $img[1][0];}
		//Video
		preg_match_all('/<iframe(.*?)<\/iframe>/si', $item, $video); 
		if($video[1][0]!=''){$full_news_video = $video;}
		
		$item = preg_replace ('!<div class="article-img  align_justify " (.*?)</div>!is', "\\2", $item); 
		$full_news_text .= '<p>'.strip_tags($item).'</p>';
	}
	//
	
	//Attach a video
	$full_news_text.=$full_news_video[0][0];
	
	//Add a link to the source
	$full_news_text .= '<noindex><p>Resource: <a target="_blank" rel="nofollow noopener" href="'.resource_url.'" title="Resource">'.resource_url.'</a></p></noindex>';
	
	//Packing into  array Full article and Picture of the full article
	$result = array('full_news_text'=>$full_news_text, 'full_news_img'=>$full_news_img, 'tags'=>$tags);

	return $result;
}
//end  
  
 //RSS parser
function parserXML($url){
	$data = implode('',file($url)); 
	$parser = xml_parser_create(); 
    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0); 
    xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1); 
    xml_parse_into_struct($parser, $data, $values, $tags); 
    xml_parser_free($parser); 
	 
	foreach ($tags as $key=>$values_arr)
	{ 
			if ($key == "item")
			{   
				for ($i=0; $i < count($values_arr); $i+=2) { 
					$offset = $values_arr[$i] + 1; 
					$len = $values_arr[$i + 1] - $offset;  
					$result_arr[] = (array_slice($values, $offset, $len)); 
				} 
			}else 
			{ 
				continue; 
			} 
	} 
	return @$result_arr;
}
//end

//Add post to WP
function addPostWP($post_title='', $post_content='', $post_status='publish', $post_author=1, $post_category = 1, $image_url='', $tags ='')
{	 // Post filtering
    remove_filter('content_save_pre', 'wp_filter_post_kses');
    remove_filter('excerpt_save_pre', 'wp_filter_post_kses');
    remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');
	
	$post_data = array(
		'post_title'    => wp_strip_all_tags($post_title),
		'post_content'  => $post_content,
		'post_status'   => $post_status,
		'post_author'   => $post_author,
		'post_category' => array($post_category)
	);

	
	$post_id = get_page_by_title($post_title, OBJECT, 'post');
	
	if($post_id->ID<1)
	{
		$post_id = wp_insert_post( $post_data );
	
	
		//Add img
		if((!empty($image_url)) && ($post_id > 0))
		{
			$attachment_id = Generate_Featured_Image( $image_url, $post_id  );
			
				//Add tags
				$tags_id = wp_set_post_tags( $post_id, $tags, true);
		}
		$result = array('post_id'=>$post_id,  'attachment_id'=>$attachment_id, 'tags_id'=>$tags_id);
	}else{
		$result = array('post_id'=>'Exist');
	}
	
	
	
	return $result;
}
//end

//Add Attachment to Wordpress
function Generate_Featured_Image($image_url, $post_id)
{
    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents($image_url);
    $filename = basename($image_url);
    if(wp_mkdir_p($upload_dir['path']))     $file = $upload_dir['path'] . '/' . $filename;
    else                                    $file = $upload_dir['basedir'] . '/' . $filename;
    file_put_contents($file, $image_data);

    $wp_filetype = wp_check_filetype($filename, null );
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name($filename),
        'post_content' => '',
        'post_status' => 'inherit'
    );
    $attach_id = wp_insert_attachment( $attachment, $file, $post_id );
   
    $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
    $res1= wp_update_attachment_metadata( $attach_id, $attach_data );
    $res2= set_post_thumbnail( $post_id, $attach_id );
	return  $res2;
}
//end
?>