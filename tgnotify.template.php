<?php

function template_main()
{
	global $context;

	echo '
	<div class="cat_bar">
   		<h3 class="catbg">',$context['page_title'],'</h3>
	</div>';
	
	echo '
	<div class="windowbg2" style="margin:10%">
		<div class="content">
			<img style="width: 2em; float:left; margin-right: 1em;" src="https://telegram.org/img/t_logo.png?1">';
	
	echo $context['tg_link_text'];

	echo '
		</div>
	</div>';
}
?>