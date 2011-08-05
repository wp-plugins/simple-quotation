<?php
/*
Plugin Name: Simple Quotation
Description: <p>Add random quotes to you blog. </p><p>You can configure this plugin: <ul><li>position of the quotes (top/botton of the page), </li><li>the html which embed the quote. </li></ul></p><p>This plugin is under GPL licence. </p>
Version: 1.0.2
Author: SedLex
Author URI: http://www.sedlex.fr/
Plugin URI: http://wordpress.org/extend/plugins/simple-quotation/
License: GPL3
*/

require_once('core.php') ; 

class quotation extends pluginSedLex {
	/** ====================================================================================================================================================
	* Initialisation du plugin
	* 
	* @return void
	*/
	static $instance = false;
	static $path = false;

	protected function _init() {
		// Configuration
		$this->pluginName = 'Simple Quotation' ; 
		$this->tableSQL = "id_quote mediumint(9) NOT NULL AUTO_INCREMENT, quote TEXT DEFAULT '', author TEXT DEFAULT '', UNIQUE KEY id_quote (id_quote)" ; 
		$this->path = __FILE__ ; 
		$this->pluginID = get_class() ; 
		
		//Init et des-init
		register_activation_hook(__FILE__, array($this,'install'));
		register_deactivation_hook(__FILE__, array($this,'uninstall'));
		
		//Paramètres supplementaires
		add_action('template_redirect', array($this, 'add_quotes')) ; 
		add_action('wp_head', array($this, 'buffer_start'));
		add_action('wp_footer', array($this, 'buffer_end'));
		add_action('wp_print_styles', array( $this, 'ajoute_inline_css'));


		add_action('wp_ajax_delete_link', array($this,'delete_link'));
		add_action('wp_ajax_validQuotes', array($this,'validQuotes')) ; 
		add_action('wp_ajax_cancelQuotes', array($this,'cancelQuotes')) ; 

	}
	/**
	 * Function to instantiate our class and make it a singleton
	 */
	public static function getInstance() {
		if ( !self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/** ====================================================================================================================================================
	* Define the default option value of the plugin
	* 
	* @return variant of the option
	*/
	function get_default_option($option) {
		switch ($option) {
			case 'top' 	: return true 	; break ; 
			case 'perso' : return "" 	; break ; 
			case 'perso2' : return "" 	; break ; 
			case 'html' : return "*<div id='quote-author'>
	<p class='quote-author'>
		<span class='author'>%author%</span>
		<span class='quote-author'>%quote%</span>
	</p>
</div>" 	; break ; 

			case 'css' 		: return "*#quote-author  { height:20px ; width:100% ; margin: 0 0 50px; background: #666666 ; clear:both;}
span.author  { line-height:20px ; padding-right:50px ; float:right; color:#AAAAAA ; font-size:10px ; font-style:italic; }
span.quote-author  { line-height:20px ; padding-right:20px ; float:right; color:#AAAAAA ; font-size:11px ; }" ; break ; 

		}
		return null ;
	}

	/** ====================================================================================================================================================
	* Add CSS
	* 
	* @return void
	*/
	
	function ajoute_inline_css() {
		$this->add_inline_css($this->get_param('css')) ; 
	}
	/** ====================================================================================================================================================
	* The configuration page
	* 
	* @return void
	*/
	function configuration_page() {
		global $wpdb;
		$wpdb->show_errors() ; 
		$table_name = $wpdb->prefix . $this->pluginID;
		
		// Store the quote if the user submit a new one...
		if (isset($_POST['add'])) {
			$query = "INSERT INTO {$table_name} (author,quote) VALUES('".mysql_real_escape_string($_POST['author'])."','".mysql_real_escape_string($_POST['newquotes'])."');" ; 
			if ($wpdb->query($query) === FALSE) {
				echo '<div class="error fade"><p>An error occurs when updating the database.</p></div>' ; 
	     	} else {
				echo '<div class="updated fade"><p>The quote has been added to the database.</p></div>' ; 
	      	}
		}
	
		?>
		<div class="wrap">
			<div id="icon-themes" class="icon32"><br></div>
			<h2><?php echo $this->pluginName ?></h2>
			<?php echo $this->signature ; ?>
			<p>This plugin will add random quotes to your pages.</p>
			<!--debut de personnalisation-->
		<?php
			$maxnb = 20 ; 
			
			$count = $wpdb->get_var('SELECT COUNT(*) FROM '.$table_name) ; 
						
			if (isset($_GET['paged'])) {
				$page_cur = $_GET['paged'] ; 
			} else {
				$page_cur = 1 ; 
			}
			
			$page_tot = ceil($count/$maxnb) ; 
			
			$page_inf = max(1,$page_cur-1) ; 
			$page_sup= min($page_tot,$page_cur+1) ; 
			
			// Mise en place de la barre de navigation
			
			$get = $_GET;
			unset($get['paged']) ;
			
			//==========================================================================================
			//
			// Mise en place du systeme d'onglet
			//		(bien mettre a jour les liens contenu dans les <li> qui suivent)
			//
			//==========================================================================================
	?>		
			<script>jQuery(function($){ $('#tabs').tabs(); }) ; </script>		
			<div id="tabs">
				<ul class="hide-if-no-js">
					<li><a href="#tab-quotes"><? echo __('Quotes',$this->pluginName) ?></a></li>					
					<li><a href="#tab-parameters"><? echo __('Parameters',$this->pluginName) ?></a></li>					
				</ul>
				<?php
				//==========================================================================================
				//
				// Premier Onglet 
				//		(bien verifier que id du 1er div correspond a celui indique dans la mise en 
				//			place des onglets)
				//
				//==========================================================================================
				?>
				<div id="tab-quotes" class="blc-section">
	
					<h3 class="hide-if-js"><? echo __('Quotes',$this->pluginName) ?></h3>
					<?php
					if ($count>0) {
					?>
					<form id="posts-filter" action="<?php echo $_SERVER['PHP_SELF'] ;?>" method="get">
						<div class="tablenav top">
							<div class="tablenav-pages">
								<?php
								// Variable cachee pour reconstruire completement l'URL de la page courante
								foreach ($get as $k => $v) {
								?>
								<input name="<?php echo $k;?>" value="<?php echo $v;?>" size="1" type="hidden"/>
								<?php
								}
								?>	
								<span class="displaying-num"><?php echo $count ; ?> items</span>
								<a class="first-page<?php if ($page_cur == 1) {echo  ' disabled' ; } ?>" <?php if ($page_cur == 1) {echo  'onclick="javascript:return false;" ' ; } ?>title="Go to the first page" href="<?php echo add_query_arg( 'paged', '1' );?>">&laquo;</a>
								<a class="prev-page<?php if ($page_cur == 1) {echo  ' disabled' ; } ?>" <?php if ($page_cur == 1) {echo  'onclick="javascript:return false;" ' ; } ?>title="Go to the previous page" href="<?php echo add_query_arg( 'paged', $page_inf );?>">&lsaquo;</a>
								<span class="paging-input"><input class="current-page" title="Current page" name="paged" value="<?php echo $page_cur;?>" size="1" type="text"> of <span class="total-pages"><?php echo $page_tot;?></span></span>
								<a class="next-page<?php if ($page_cur == $page_tot) {echo  ' disabled' ; } ?>" <?php if ($page_cur == $page_tot) {echo  'onclick="javascript:return false;" ' ; } ?>title="Go to the next page" href="<?php echo add_query_arg( 'paged', $page_sup );?>">&rsaquo;</a>
								<a class="last-page<?php if ($page_cur == $page_tot) {echo  ' disabled' ; } ?>" <?php if ($page_cur == $page_tot) {echo  'onclick="javascript:return false;" ' ; } ?>title="Go to the last page" href="<?php echo add_query_arg( 'paged', $page_tot );?>">&raquo;</a>			
								<br class="clear">
							</div>
						</div>
					</form>
					<?php 
					}
					// Mise en place du tableau 
					?>
					<table class="widefat fixed" cellspacing="0">
						<thead>
							<tr>
								<tr>
									<th id="cb" class="manage-column column-columnname" scope="col">Author</th> 
									<th id="columnname" class="manage-column column-columnname" scope="col">Quote</th>
								</tr>
							</tr>
						</thead>
			
						<tfoot>
							<tr>
								<tr>
									<th class="manage-column column-columnname" scope="col">Author</th>
									<th class="manage-column column-columnname" scope="col">Quote</th>
								</tr>
							</tr>
						</tfoot>
						<tbody>
						<?php 
						// lignes du tableau
						// boucle sur les differents elements
						$query = 'SELECT id_quote,author,quote FROM '.$table_name.' LIMIT '.$maxnb.' OFFSET '.(($page_cur-1)*$maxnb) ; 
						$result = $wpdb->get_results($query) ; 

						foreach ($result as $r) {
							$ligne++ ; 
							
						?>
							<tr class="<?php if ($ligne%2==1) {echo  'alternate' ; } ?>" valign="top" id="ligne<?php echo $r->id_quote ; ?>"> 
								<td class="column-columnname">
									<b><?php echo stripslashes($r->author) ; ?></b>
								</td>
								<td class="column-columnname">
									<span id="quote<?php echo $r->id_quote; ?>" ><?php echo stripslashes($r->quote) ; ?></span>
									<img src="<?php echo WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)) ?>img/ajax-loader.gif" id="wait<?php echo $r->id_quote ; ?>" style="display: none;" />
									<div class="row-actions" id="button<?php echo $r->id_quote ; ?>">
										<span><a href="#" onclick="javascript:return modifyButtonQuote(<?php echo $r->id_quote ; ?>) ;" id="modify<?php echo $r->id_quote ; ?>">Modify</a> |</span>
										<span><a href="#" onclick="javascript:return deleteButtonQuote(<?php echo $r->id_quote ; ?>) ;" id="delete<?php echo $r->id_quote ; ?>">Delete</a></span>
									</div>
								</td>
							</tr>
						<?php 
						}
						// Fin du tableau
						?>
						</tbody>
					</table>
					<br/>
					
					<?php 
					// Add a box in order to configure new quote 
					?>
					<div class="metabox-holder" style="width: 100%">
						<div class="meta-box-sortables">
						
							<div class="postbox">
								<h3 class="hndle"><span>Add a new quote</span></h3>
								<div class="inside" style="padding: 5px 10px 5px 20px;">
									<form method='post' action='<?echo $_SERVER["REQUEST_URI"]?>#tab-quotes'>
										<label for='author'>Author:</label>
										<input name='author' id='author' type='text' value='' size='40'/><br/>
										<label for='newquotes'>Quote:</label>
										<textarea name='newquotes' id='newquotes' rows="4" cols="100%"></textarea>
										<div class="submit">
											<input type="submit" name="add" class='button-primary validButton' value="Add the quote" />
										</div>
									</form>
								</div>
							</div>
							
						</div>
					</div>

				</div>
				<?php
				//==========================================================================================
				//
				// Deuxieme Onglet 
				//		(bien verifier que id du 1er div correspond a celui indique dans la mise en 
				//			place des onglets)
				//
				//==========================================================================================
				?>
				<div id="tab-parameters" class="blc-section">
				
					<h3 class="hide-if-js"><? echo __('Parameters',$this->pluginName) ?></h3>
					<p><?php echo __('Here is the parameters of the plugin. Please modify them at your convenience.',$this->pluginName) ; ?> </p>
				
					<?php
					$params = new parametersSedLex($this, 'tab-parameters') ; 
					$params->add_title(__('The quote will be displayed:',$this->pluginName)) ; 
					$params->add_param('top', __('In top of the page:',$this->pluginName)) ; 
					$params->add_param('perso', __('After this HTML tag in the page:',$this->pluginName)) ; 
					$params->add_param('perso2', __('Before this HTML tag in the page:',$this->pluginName)) ; 
					$params->add_comment(__('For instance, if you put <code>&lt;body[^>]*&gt;</code>, the quote will be printed AFTER/BEFORE the HTML tag <code>body</code>',$this->pluginName)."<br/>".__('If this input is empty, it means that you do not want to print the quote at a personal location',$this->pluginName)) ; 	
					$params->add_title(__('How the quote will be displayed?',$this->pluginName)) ; 
					$params->add_param('html', __('With this HTML code:',$this->pluginName)) ; 
					$comment = __('The standard HTML is:',$this->pluginName); 
					$comment .= "<br/><span style='margin-left: 30px;'><code>&lt;div id='quote-author'&gt;</code></span><br/>" ; 
					$comment .= "<span style='margin-left: 60px;'><code>&lt;p class='quote-author'&gt;</code></span><br/>" ; 
					$comment .= "<span style='margin-left: 90px;'><code>&lt;span class='author'&gt;%author%&lt;/span&gt;</code></span><br/>" ; 
					$comment .= "<span style='margin-left: 90px;'><code>&lt;span class='quote-author'&gt;%quote%&lt;/span&gt;</code></span><br/>" ; 
					$comment .= "<span style='margin-left: 60px;'><code>&lt;/p&gt;</code></span><br/>" ; 
					$comment .= "<span style='margin-left: 30px;'><code>&lt;/div&gt;</code></span><br/>" ; 
					$comment .= "<code>%author%</code> = The name of the author</span><br/>" ; 
					$comment .= "<code>%quote%</code> = The given quote</span><br/>" ; 
					$params->add_comment($comment) ; 	
					$params->add_param('css', __('With this CSS style:',$this->pluginName)) ; 
					$comment = __('The standard CSS is:',$this->pluginName); 
					$comment .= "<br/><span style='margin-left: 30px;'><code>#quote-author  { height:20px ; width:100% ; margin: 0 0 50px; background: #666666 ; clear:both;}</code></span><br/>" ; 
					$comment .= "<br/><span style='margin-left: 30px;'><code>span.author  { line-height:20px ; padding-right:50px ; float:right; color:#AAAAAA ; font-size:10px ; font-style:italic; }</code></span><br/>" ; 
					$comment .= "<br/><span style='margin-left: 30px;'><code>span.quote-author  { line-height:20px ; padding-right:20px ; float:right; color:#AAAAAA ; font-size:11px ; }</code></span><br/>" ; 
					$params->add_comment($comment) ; 	
					$params->flush() ; 
					
					?>
				</div>
			</div>
			<!--fin de personnalisation-->
			<?php echo $this->signature ; ?>
		</div>
		<?php
	}

	/** ====================================================================================================================================================
	* Callback for Delete Link
	* 
	* @return void
	*/
	function delete_link() {
		global $wpdb;
		$wpdb->show_errors() ; 
		$table_name = $wpdb->prefix . $this->pluginID;
		
		// get the arguments
		$idLink = $_POST['idLink'];
		// Empty the database for the given idLink
		$q = "DELETE FROM {$table_name} WHERE id_quote=".mysql_real_escape_string($idLink) ; 
		$wpdb->query( $q ) ;
		return "coucou" ; 
		die();
	}
	
	/** ====================================================================================================================================================
	* Callback for valid Button
	* 
	* @return void
	*/
	function validQuotes() {
		global $wpdb;
		$table_name = $wpdb->prefix . $this->pluginID;
		
		// get the arguments
		$id = $_POST['id'];
		$quote = $_POST['quote'];
		
		// Empty the database for the given idLink
		$q = "UPDATE {$table_name} SET quote = '".mysql_real_escape_string($quote)."' WHERE id_quote=".mysql_real_escape_string($id) ; 
		$wpdb->query( $q ) ;
		echo stripslashes($quote) ; 
		die();
	}


	/** ====================================================================================================================================================
	* Callback for cancel button
	* 
	* @return void
	*/
	function cancelQuotes() {
		global $wpdb;
		$table_name = $wpdb->prefix . $this->pluginID;
		
		// get the arguments
		$id = $_POST['id'];
		// Get a entry
		$q = "SELECT * FROM {$table_name} WHERE id_quote=".mysql_real_escape_string($id) ; 
		$result = $wpdb->get_results($q) ; 

		foreach ($result as $r) {
			echo stripslashes($r->quote) ; 
		}
		die();
	}
	
	/** ====================================================================================================================================================
	* Get a random quotes
	* 
	* @return void
	*/
	
	function get_quote() {
		global $wpdb;
		$table_name = $wpdb->prefix . $this->pluginID;

		$q = "SELECT * FROM {$table_name}"; 
		$result = $wpdb->get_results($q, ARRAY_A) ; 
		$n = rand(0, count($result)-1) ; 
		
		$quote = stripslashes($result[$n]['quote']) ; 
		$author = stripslashes($result[$n]['author']) ; 
		$html = $this->get_param('html') ; 
		return str_replace("%quote%", $quote, str_replace("%author%",$author, $html)) ; 
	}
	/** ====================================================================================================================================================
	* Add the random quotes
	* 
	* @return void
	*/
	
	function add_quotes() {
		if ($this->get_param('top')) {
			echo $this->get_quote() ;
		}
	}
	
	/** ====================================================================================================================================================
	* Allow to insert the quote after a specific tag
	* 
	* @return void
	*/
	
	function buffer_start() {
		ob_start();
	}
	function buffer_end() {
		$content = ob_get_clean();
		
		//Top
		if ($this->get_param('top')) {
			$insert = $this->get_quote() ;
			$content = preg_replace('#<body[^>]*>#i',"$0".$insert,$content);
		}		
		// after Specfic place
		$after = trim($this->get_param('perso')) ; 
		if ($after != "") {
			$insert = $this->get_quote() ;
			$content = preg_replace('#'.$after.'#i',"$0".$insert,$content);
		}
		
		// after Specfic place
		$before = trim($this->get_param('perso2')) ; 
		if ($before != "") {
			$insert = $this->get_quote() ;
			$content = preg_replace('#'.$before.'#i',$insert."$0",$content);
		}

		//$insert = "coucou" ; 
		
		echo $content;
	}
	

}

$quotation = quotation::getInstance();

?>