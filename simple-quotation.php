<?php
/*
Plugin Name: Simple Quotation
Description: <p>Add random quotes to you blog. </p><p>You can configure this plugin: <ul><li>position of the quotes (top/botton of the page), </li><li>the html which embed the quote. </li></ul></p><p>This plugin is under GPL licence. </p>
Version: 1.0.3
Author: SedLex
Author Email: sedlex@sedlex.fr
Framework Email: sedlex@sedlex.fr
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
		</div>
		<div style="padding:20px;">
			<?php echo $this->signature ; ?>
			<p><?php echo __('This plugin will add random quotes to your pages.', $this->pluginID) ; ?></p>
		<?php
			$maxnb = 20 ; 
			$count = $wpdb->get_var('SELECT COUNT(*) FROM '.$table_name) ; 	
			
			//==========================================================================================
			//
			// Mise en place du systeme d'onglet
			//		(bien mettre a jour les liens contenu dans les <li> qui suivent)
			//
			//==========================================================================================

			$tabs = new adminTabs() ; 
			
			ob_start() ; 
			
				//==========================================================================================
				//
				// Premier Onglet 
				//		(bien verifier que id du 1er div correspond a celui indique dans la mise en 
				//			place des onglets)
				//
				//==========================================================================================

				$table = new adminTable($count, $maxnb) ; 
				$page_cur = $table->current_page() ; 
				$table->title(array(__('Author', $this->pluginID), __('Quotes', $this->pluginID)) ) ; 

				// lignes du tableau
				// boucle sur les differents elements
				$query = 'SELECT id_quote,author,quote FROM '.$table_name.' LIMIT '.$maxnb.' OFFSET '.(($page_cur-1)*$maxnb) ; 
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
					<img src="<?php echo WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)) ?>img/ajax-loader.gif" id="wait<?php echo $r->id_quote ; ?>" style="display: none;" />
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
					<form method='post' action='<?echo $_SERVER["REQUEST_URI"]?>#tab-quotes'>
						<label for='author'><?php echo __('Author:', $this->pluginID) ; ?></label>
						<input name='author' id='author' type='text' value='' size='40'/><br/>
						<label for='newquotes'><?php echo __('Quote:', $this->pluginID) ; ?></label>
						<textarea name='newquotes' id='newquotes' rows="4" cols="100%"></textarea>
						<div class="submit">
							<input type="submit" name="add" class='button-primary validButton' value="Add the quote" />
						</div>
					</form>
					<?php
				$box = new boxAdmin (__('Add a new quote', $this->pluginID), ob_get_clean()) ; 
				echo $box->flush() ; 
				
			$tabs->add_tab(__('Quotes',  $this->pluginID), ob_get_clean() ) ; 	

			ob_start() ; 
				?>
				<p><?php echo __('Here is the parameters of the plugin. Please modify them at your convenience.',$this->pluginID) ; ?> </p>
				<?php
				$params = new parametersSedLex($this, 'tab-parameters') ; 
				$params->add_title(__('The quote will be displayed:',$this->pluginID)) ; 
				$params->add_param('top', __('In top of the page:',$this->pluginID)) ; 
				$params->add_param('perso', __('After this HTML tag in the page:',$this->pluginID)) ; 
				$params->add_param('perso2', __('Before this HTML tag in the page:',$this->pluginID)) ; 
				$params->add_comment(sprintf(__('For instance, if you put %s, the quote will be printed AFTER/BEFORE the HTML tag %s',$this->pluginID), "<code>&lt;body[^>]*&gt;</code>", "<code>body</code>")."<br/>".__('If this input is empty, it means that you do not want to print a quote at a custom location',$this->pluginID)) ; 	
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
			$tabs->add_tab(__('Parameters',  $this->pluginID), ob_get_clean() ) ; 	
		
			ob_start() ; 
				$plugin = str_replace("/","",str_replace(basename(__FILE__),"",plugin_basename( __FILE__))) ; 
				$trans = new translationSL($this->pluginID, $plugin) ; 
				$trans->enable_translation() ; 
			$tabs->add_tab(__('Manage translations',  $this->pluginID), ob_get_clean() ) ; 	

			ob_start() ; 
				echo __('This form is an easy way to contact the author and to discuss issues / incompatibilities / etc.',  $this->pluginID) ; 
				$plugin = str_replace("/","",str_replace(basename(__FILE__),"",plugin_basename( __FILE__))) ; 
				$trans = new feedbackSL($plugin, $this->pluginID) ; 
				$trans->enable_feedback() ; 
			$tabs->add_tab(__('Give feedback',  $this->pluginID), ob_get_clean() ) ; 	
			
			ob_start() ; 
				echo "<p>".__('Here is the plugins developped by the author',  $this->pluginID) ."</p>" ; 
				$trans = new otherPlugins("sedLex", array('wp-pirates-search')) ; 
				$trans->list_plugins() ; 
			$tabs->add_tab(__('Other possible plugins',  $this->pluginID), ob_get_clean() ) ; 	
			
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