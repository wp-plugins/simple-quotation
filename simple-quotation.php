<?php
/*
Plugin Name: Simple Quotation
Plugin Tag: quotation, cite, citation, quote
Description: <p>Add random quotes to you blog. </p><p>You can configure this plugin: </p><ul><li>position of the quotes (top/botton of the page), </li><li>the html which embed the quote. </li></ul><p>This plugin is under GPL licence. </p>
Version: 1.3.1


Author: SedLex
Author Email: sedlex@sedlex.fr
Framework Email: sedlex@sedlex.fr
Author URI: http://www.sedlex.fr/
Plugin URI: http://wordpress.org/plugins/simple-quotation/
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
	var $path = false;

	protected function _init() {
		global $wpdb ; 
		// Configuration
		$this->pluginName = 'Simple Quotation' ; 
		$this->tableSQL = "id_quote mediumint(9) NOT NULL AUTO_INCREMENT, quote TEXT DEFAULT '', author TEXT DEFAULT '', UNIQUE KEY id_quote (id_quote)" ; 
		$this->path = __FILE__ ; 
		$this->table_name = $wpdb->prefix . "pluginSL_" . get_class() ; 
		$this->pluginID = get_class() ; 
		
		//Init et des-init
		register_activation_hook(__FILE__, array($this,'install'));
		register_deactivation_hook(__FILE__, array($this,'deactivate'));
		register_uninstall_hook(__FILE__, array('quotation','uninstall_removedata'));
		
		//Parametres supplementaires
		add_action('template_redirect', array($this, 'add_quotes')) ; 
		add_action('wp_head', array($this, 'buffer_start'));
		add_action('wp_footer', array($this, 'buffer_end'));
		add_action('init', array( $this, 'test_if_export_quotes'), 1);

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
	* In order to uninstall the plugin, few things are to be done ... 
	* (do not modify this function)
	* 
	* @return void
	*/
	
	public function uninstall_removedata () {
		global $wpdb ;
		// DELETE OPTIONS
		delete_option('quotation'.'_options') ;
		if (is_multisite()) {
			delete_site_option('quotation'.'_options') ;
		}
		
		// DELETE SQL
		if (function_exists('is_multisite') && is_multisite()){
			$old_blog = $wpdb->blogid;
			$old_prefix = $wpdb->prefix ; 
			// Get all blog ids
			$blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM ".$wpdb->blogs));
			foreach ($blogids as $blog_id) {
				switch_to_blog($blog_id);
				$wpdb->query("DROP TABLE ".str_replace($old_prefix, $wpdb->prefix, $wpdb->prefix . "pluginSL_" . 'quotation')) ; 
			}
			switch_to_blog($old_blog);
		} else {
			$wpdb->query("DROP TABLE ".$wpdb->prefix . "pluginSL_" . 'quotation' ) ; 
		}
		
		// DELETE FILES if needed
		//SLFramework_Utils::rm_rec(WP_CONTENT_DIR."/sedlex/my_plugin/"); 
		$plugins_all = 	get_plugins() ; 
		$nb_SL = 0 ; 	
		foreach($plugins_all as $url => $pa) {
			$info = pluginSedlex::get_plugins_data(WP_PLUGIN_DIR."/".$url);
			if ($info['Framework_Email']=="sedlex@sedlex.fr"){
				$nb_SL++ ; 
			}
		}
		if ($nb_SL==1) {
			SLFramework_Utils::rm_rec(WP_CONTENT_DIR."/sedlex/"); 
		}
	}
	
	/**==========================================================================================================================================
	 * Upgrade function
	 */
	 
	public function _update() {
		global $wpdb;
		$table_name = $this->table_name;
		$old_table_name = $wpdb->prefix . $this->pluginID ; 
		
		// This update aims at changing the table name from the old table name to the new one
		if ($wpdb->get_var("show tables like '$old_table_name'") == $old_table_name) {
			if ($wpdb->get_var("SELECT COUNT(*) FROM ".$table_name) == 0) {
				// We delete the new created table
				$wpdb->query("DROP TABLE ".$table_name) ; 
				// We change the name of the old table
				$wpdb->query("RENAME TABLE ".$old_table_name." TO ".$table_name) ; 
				// Gestion de l'erreur
				ob_start() ; 
				$wpdb->print_error();
				$result = ob_get_clean() ; 
				if (strlen($result)>0) {
					echo $result ; 
					die() ; 
				}
			} else {
				echo sprintf(__("Couldn't drop the table %s because there is entries in it. Please merge the two tables %s and %s in a single table with name %s", $this->pluginID), $table_name, $table_name, $old_table_name, $table_name) ; 
				die() ; 
			}
		}
		
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
	* Init css for the public side
	* If you want to load a style sheet, please type :
	*	<code>$this->add_inline_css($css_text);</code>
	*	<code>$this->add_css($css_url_file);</code>
	*
	* @return void
	*/
	
	function _public_css_load() {	
		$this->add_inline_css($this->get_param('css')) ; 
	}
	
	
	/** ====================================================================================================================================================
	* Test if a quote file should be outputted
	* 
	* @return void
	*/
	
	function test_if_export_quotes() {
		global $wpdb;
		$wpdb->show_errors() ; 
		$table_name = $this->table_name;

		if (isset($_POST['export'])) {
			header("Content-Type: application/force-download; name=\"export_quotes_".date("Ymd").".txt\"");
			header("Content-Transfer-Encoding: binary");
			header("Content-Disposition: attachment; filename=\"export_quotes_".date("Ymd").".txt\"");
			header("Expires: 0");
			header("Cache-Control: no-cache, must-revalidate");
			header("Pragma: no-cache");

			// lignes du tableau
			// boucle sur les differents elements
			$query = 'SELECT id_quote,author,quote FROM '.$table_name.' ORDER BY author ASC' ; 
			$result = $wpdb->get_results($query) ; 

			foreach ($result as $r) {
				echo SLFramework_Utils::convertUTF8(stripslashes($r->quote))."\n".SLFramework_Utils::convertUTF8(stripslashes($r->author))."\n" ;
			}
			
			exit ; 
		}	
	}
	
	/** ====================================================================================================================================================
	* The configuration page
	* 
	* @return void
	*/
	function configuration_page() {
		global $wpdb;
		$wpdb->show_errors() ; 
		$table_name = $this->table_name;
		
		// Store the quote if the user submit a new one...
		if (isset($_POST['add'])) {
			$query = "INSERT INTO {$table_name} (author,quote) VALUES('".esc_sql($_POST['author'])."','".esc_sql($_POST['newquotes'])."');" ; 
			if ($wpdb->query($query) === FALSE) {
				echo '<div class="error fade"><p>'.__('An error occurs when updating the database.', $this->pluginID).'</p></div>' ; 
	     	} else {
				echo '<div class="updated fade"><p>'.__('The quote has been added to the database.', $this->pluginID).'</p></div>' ; 
	      	}
		}
		
		// Store the quote if the user submit a file...
		if (isset($_POST['import'])) {
			if (is_file($_FILES['fileImport']['tmp_name'])) {
				$lines = @file($_FILES['fileImport']['tmp_name']) ; 
				$quote = true ; 
				$author = false ; 
				$success = true ; 
				$nb_import = 0 ; 
				$tmp_quote = "" ; 
				foreach ($lines as $l) {
					if ($quote) {
						$tmp_quote = esc_sql(SLFramework_Utils::convertUTF8($l)) ; 
						$quote = false ; 
						$author = true ; 
					} else {
						$quote = true ; 
						$author = false ; 
						$query = "INSERT INTO {$table_name} (author,quote) VALUES('".esc_sql(SLFramework_Utils::convertUTF8($l))."','".$tmp_quote."');" ; 
						if ($wpdb->query($query) === FALSE) {
							$success = false ; 
						} else {
							$nb_import ++ ;
						}
					}
				}
				if ($success==false) {
					if ($nb_import==0) {
						echo '<div class="error fade"><p>'.__('An error occurs when updating the database.', $this->pluginID).'</p></div>' ; 
					} else {
						echo '<div class="error fade"><p>'.sprintf(__('An error occurs when updating the database. Nevertheless %s sentences has been imported successfully.', $this->pluginID), $nb_import).'</p></div>' ; 
					}
				} else {
					echo '<div class="updated fade"><p>'.sprintf(__('%s quotes have been added to the database.', $this->pluginID), $nb_import).'</p></div>' ; 
				}
			}
		}

		?>
		<div class="plugin-titleSL">
			<h2><?php echo $this->pluginName ?></h2>
		</div>
		
		<div class="plugin-contentSL">		
			<?php echo $this->signature ; 
			
			$maxnb = 20 ; 
			$count = $wpdb->get_var('SELECT COUNT(*) FROM '.$table_name) ; 	
			
			// On verifie que les droits sont corrects
			$this->check_folder_rights( array() ) ; 
			
			//==========================================================================================
			//
			// Mise en place du systeme d'onglet
			//		(bien mettre a jour les liens contenu dans les <li> qui suivent)
			//
			//==========================================================================================

			$tabs = new SLFramework_Tabs() ; 
			
			ob_start() ; 
						
				//==========================================================================================
				//
				// Premier Onglet 
				//		(bien verifier que id du 1er div correspond a celui indique dans la mise en 
				//			place des onglets)
				//
				//==========================================================================================

				$table = new SLFramework_Table($count, $maxnb) ; 
				$page_cur = $table->current_page() ; 
				$table->title(array(__('Author', $this->pluginID), __('Quotes', $this->pluginID)) ) ; 

				// lignes du tableau
				// boucle sur les differents elements
				$query = 'SELECT id_quote,author,quote FROM '.$table_name.' ORDER BY author ASC LIMIT '.$maxnb.' OFFSET '.(($page_cur-1)*$maxnb) ; 
				$result = $wpdb->get_results($query) ; 

				foreach ($result as $r) {
					ob_start() ; 
					?>
					<i><?php echo stripslashes($r->author) ; ?></i>
					<?php
					$cel1 = new adminCell(ob_get_clean()) ; 
					
					ob_start() ; 
					?>
					<span id="quote<?php echo $r->id_quote; ?>" ><?php echo stripslashes($r->quote) ; ?></span>
					<img src="<?php echo plugin_dir_url("/").'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)) ?>img/ajax-loader.gif" id="wait<?php echo $r->id_quote ; ?>" style="display: none;" />
					<?php
					$cel2 = new adminCell(ob_get_clean()) ; 	
					$cel2->add_action(__("Modify", $this->pluginID), "modifyButtonQuote") ; 
					$cel2->add_action(__("Delete", $this->pluginID), "deleteButtonQuote") ; 
					$table->add_line(array($cel1, $cel2), $r->id_quote) ; 
				}
				echo $table->flush() ; 
				
				echo "<br/>" ;
				
				ob_start() ; 
					?>
					<form method='post' action='<?php echo $_SERVER["REQUEST_URI"]?>'>
						<label for='author'><?php echo __('Author:', $this->pluginID) ; ?></label>
						<input name='author' id='author' type='text' value='' size='40'/><br/>
						<label for='newquotes'><?php echo __('Quote:', $this->pluginID) ; ?></label>
						<textarea name='newquotes' id='newquotes' rows="4" cols="100%"></textarea>
						<div class="submit">
							<input type="submit" name="add" class='button-primary validButton' value="<?php echo __('Add the quote', $this->pluginID) ; ?>" />
						</div>
					</form>
					<?php
				$box = new SLFramework_Box (__('Add a new quote', $this->pluginID), ob_get_clean()) ; 
				echo $box->flush() ; 
				
				ob_start() ; 
					?>
					<form method='post' enctype='multipart/form-data' action='<?php echo $_SERVER["REQUEST_URI"]?>'>
						<p style='color: #a4a4a4;'><?php echo __("The file should be a UTF-8 text file which contains 2 lines per entry (one for the quote, and one for the author).", $this->pluginID) ; ?></p>
						<label for='fileImport'><?php echo __('Select the file:', $this->pluginID) ; ?></label>
						<input name='fileImport' id='fileImport' type='file'/><br/>
						<div class="submit">
							<input type="submit" name="import" class='button-primary validButton' value="<?php echo __('Import the file', $this->pluginID) ; ?>" />
							<input type="submit" name="export" class='button-primary validButton' value="<?php echo __('Export the quotes', $this->pluginID) ; ?>" />
						</div>
					</form>
					<?php
				$box = new SLFramework_Box (__('Import/Export multiple quotes', $this->pluginID), ob_get_clean()) ; 
				echo $box->flush() ; 
				
			$tabs->add_tab(__('Quotes',  $this->pluginID), ob_get_clean() ) ; 	

			// HOW To
			ob_start() ;
				echo "<p>".__("This plugin allows the display of random quotes on your frontpage.", $this->pluginID)."</p>" ; 
			$howto1 = new SLFramework_Box (__("Purpose of that plugin", $this->pluginID), ob_get_clean()) ; 
			ob_start() ;
				echo "<p>".__('Just configure the position of the quotes in the configuration tab.', $this->pluginID)."</p>" ; 
			$howto2 = new SLFramework_Box (__("How to display the quotes?", $this->pluginID), ob_get_clean()) ; 
			ob_start() ;
				echo "<p>".__('Customize your quotes in the quote tab and enjoy...', $this->pluginID)."</p>" ; 
			$howto3 = new SLFramework_Box (__("What quotes will be displayed?", $this->pluginID), ob_get_clean()) ; 
			ob_start() ;
				 echo $howto1->flush() ; 
				 echo $howto2->flush() ; 
				 echo $howto3->flush() ; 
			$tabs->add_tab(__('How To',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_how.png") ; 				


			ob_start() ; 
				?>
				<p><?php echo __('Here is the parameters of the plugin. Please modify them at your convenience.',$this->pluginID) ; ?> </p>
				<?php
				$params = new SLFramework_Parameters($this, 'tab-parameters') ; 
				$params->add_title(__('The quote will be displayed:',$this->pluginID)) ; 
				$params->add_param('top', __('In top of the page:',$this->pluginID)) ; 
				$params->add_param('perso', __('After this HTML tag in the page:',$this->pluginID)) ; 
				$params->add_comment(sprintf(__('For instance, if you put %s, the quote will be printed AFTER the HTML tag %s',$this->pluginID), "<code>&lt;body[^>]*&gt;</code>", "<code>body</code>")."<br/>".__('If this input is empty, it means that you do not want to print a quote at a custom location',$this->pluginID)) ; 	
				$params->add_param('perso2', __('Before this HTML tag in the page:',$this->pluginID)) ; 
				$params->add_comment(sprintf(__('For instance, if you put %s, the quote will be printed BEFORE the HTML tag %s',$this->pluginID), "<code>&lt;div id='footer'&gt;</code>", "<code>body</code>")."<br/>".__('If this input is empty, it means that you do not want to print a quote at a custom location',$this->pluginID)) ; 	
				$params->add_title(__('How the quote will be displayed?',$this->pluginID)) ; 
				$params->add_param('html', __('With this HTML code:',$this->pluginID)) ; 
				$comment = __('The standard HTML is:',$this->pluginID); 
				$comment .= "<br/><span style='margin-left: 30px;'><code>&lt;div id='quote-author'&gt;</code></span><br/>" ; 
				$comment .= "<span style='margin-left: 60px;'><code>&lt;p class='quote-author'&gt;</code></span><br/>" ; 
				$comment .= "<span style='margin-left: 90px;'><code>&lt;span class='author'&gt;%author%&lt;/span&gt;</code></span><br/>" ; 
				$comment .= "<span style='margin-left: 90px;'><code>&lt;span class='quote-author'&gt;%quote%&lt;/span&gt;</code></span><br/>" ; 
				$comment .= "<span style='margin-left: 60px;'><code>&lt;/p&gt;</code></span><br/>" ; 
				$comment .= "<span style='margin-left: 30px;'><code>&lt;/div&gt;</code></span><br/>" ; 
				$comment .= "<code>%author%</code> = ".__('The name of the author',$this->pluginID)."</span><br/>" ; 
				$comment .= "<code>%quote%</code> = ".__('The given quote',$this->pluginID)."</span><br/>" ; 
				$params->add_comment($comment) ; 	
				$params->add_param('css', __('With this CSS style:',$this->pluginID)) ; 
				$comment = __('The standard CSS is:',$this->pluginID); 
				$comment .= "<br/><span style='margin-left: 30px;'><code>#quote-author  { height:20px ; width:100% ; margin: 0 0 50px; background: #666666 ; clear:both;}</code></span><br/>" ; 
				$comment .= "<br/><span style='margin-left: 30px;'><code>span.author  { line-height:20px ; padding-right:50px ; float:right; color:#AAAAAA ; font-size:10px ; font-style:italic; }</code></span><br/>" ; 
				$comment .= "<br/><span style='margin-left: 30px;'><code>span.quote-author  { line-height:20px ; padding-right:20px ; float:right; color:#AAAAAA ; font-size:11px ; }</code></span><br/>" ; 
				$params->add_comment($comment) ; 	
				$params->flush() ; 
			$tabs->add_tab(__('Parameters',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_param.png") ; 	
		
			ob_start() ; 
				$plugin = str_replace("/","",str_replace(basename(__FILE__),"",plugin_basename( __FILE__))) ; 
				$trans = new SLFramework_Translation($this->pluginID, $plugin) ; 
				$trans->enable_translation() ; 
			$tabs->add_tab(__('Manage translations',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_trad.png") ; 	

			ob_start() ; 
				$plugin = str_replace("/","",str_replace(basename(__FILE__),"",plugin_basename( __FILE__))) ; 
				$trans = new SLFramework_Feedback($plugin, $this->pluginID) ; 
				$trans->enable_feedback() ; 
			$tabs->add_tab(__('Give feedback',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_mail.png") ; 	
			
			ob_start() ; 
				$trans = new SLFramework_OtherPlugins("sedLex", array('wp-pirates-search')) ; 
				$trans->list_plugins() ; 
			$tabs->add_tab(__('Other plugins',  $this->pluginID), ob_get_clean() , plugin_dir_url("/").'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_plug.png") ; 	
			
			echo $tabs->flush() ; 
			
			echo $this->signature ; ?>
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
		$table_name = $this->table_name;
		
		// get the arguments
		$idLink = $_POST['idLink'];
		// Empty the database for the given idLink
		$q = "DELETE FROM {$table_name} WHERE id_quote=".esc_sql($idLink) ; 
		$wpdb->query( $q ) ;
		die();
	}
	
	/** ====================================================================================================================================================
	* Callback for valid Button
	* 
	* @return void
	*/
	function validQuotes() {
		global $wpdb;
		$table_name = $this->table_name;
		
		// get the arguments
		$id = $_POST['id'];
		$quote = $_POST['quote'];
		
		// Empty the database for the given idLink
		$q = "UPDATE {$table_name} SET quote = '".esc_sql($quote)."' WHERE id_quote=".esc_sql($id) ; 
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
		$table_name = $this->table_name;
		
		// get the arguments
		$id = $_POST['id'];
		// Get a entry
		$q = "SELECT * FROM {$table_name} WHERE id_quote=".esc_sql($id) ; 
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
		$table_name = $this->table_name;

		$q = "SELECT * FROM {$table_name}"; 
		$result = $wpdb->get_results($q, ARRAY_A) ; 
		if (count($result)>0) {
			$n = rand(0, count($result)-1) ; 
			$quote = stripslashes($result[$n]['quote']) ; 
			$author = stripslashes($result[$n]['author']) ; 
			$html = $this->get_param('html') ; 
			return str_replace("%quote%", $quote, str_replace("%author%",$author, $html)) ; 
		} else {
			return "" ; 
		}
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
		
		echo $content;
	}
	

}

$quotation = quotation::getInstance();

?>