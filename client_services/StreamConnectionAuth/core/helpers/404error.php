<div style="text-align: center">
	<h1 class="s404">404 <?=Text::_('global.error');?> <sup>EM3</sup></h1>
	<h2 class="s404"><?= Text::_('404') ;?></h2>
	<p><?= Text::_('404_desc'); ?></p>
	<p><a href="<?=URL_BASE;?>">&raquo; <?=Text::_('global.mainpage');?> &laquo;</a></p>
	<?php
	if ($message != '') {
		echo(Text::_('404_message'));
		echo($message);
	}
	?>
</div>