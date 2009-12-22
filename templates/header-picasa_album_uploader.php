<?php
/**
 *	@package picasa_album_uploader
 */

	// Need jQuery for this page
	wp_enqueue_script('jquery');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>

<head profile="http://gmpg.org/xfn/11">
<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php bloginfo('charset'); ?>" />

<title><?php bloginfo('name'); ?> Picasa MiniBrowser </title>

<?php  
  wp_head(); 
?>

<link rel="stylesheet" href="<?php bloginfo('stylesheet_url'); ?>" type="text/css" media="screen" />

<!-- If user elects to change image size, the hidden fields need to be updated appropriately. -->
<script type="text/javascript">
    function chURL(psize){
        \$("input[type='hidden']").each(function()
        {
            this.name = this.name.replace(/size=.*/,"size="+psize);
        });
    }
</script>

</head>
<body>
	<div id="page">
		<div id="header" role="banner">
			<div id="headerimg">
				<h1><a href="<?php echo get_option('home'); ?>/"><?php bloginfo('name'); ?></a></h1>
				<div class="description"><?php bloginfo('description'); ?></div>
			</div>
		</div>

		<hr />
