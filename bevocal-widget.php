<?php
/*
Plugin Name: BeVocal Widget
Plugin URI: http://www.bevocal.org/widget
Description: Provides a block of fetched data from the beVocal council and police data service
Author: Chris Ivens
Version: 0.1
Author URI: http://www.joltbox.co.uk
*/
/*  

	Copyright © 2010  Chris Ivens

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * BeVocalWidget Class
 */
class BeVocalWidget extends WP_Widget {

	private $ward_data;
	private $instance_vars;
	
    /** constructor */
    function BeVocalWidget() {
        parent::WP_Widget(false, $name = 'BeVocalWidget');
        
        add_action('wp_print_scripts', array(__CLASS__, 'bv_add_scripts'));
    }

    /** @see WP_Widget::widget */
    function widget($args, $instance) {
        extract( $args );
        $this->instance_vars = $instance;
        
        $title = apply_filters('widget_title', $instance['title']);
        
		$url = 'http://lookup.bebirmingham.org.uk/councildata/index.php/api_v1/search/postcode/' . $instance['postcode'];
		
		if($instance['limit'] && $instance['limit'] > 0) {
			$url .= '?limit=' . (int)$instance['limit'];
		}
		
		$xml = simplexml_load_file($url);
		
		if($xml->error) {
			$messages = $xml->error->children();
			
			echo $before_widget;
			if($title) echo $before_title . $title . $after_title;
			echo '<ul class="errors">';
			
			foreach($messages as $message) {
				echo '<li class="error">' . $message . '</li>';
			}
			
			echo '</ul>';
		}
		
		$ward_data = $xml->xpath('/*/ol_data/ward');
		$this->ward_data = array_shift($ward_data);
	
		echo $before_widget;
		
		?>
		
		<div id="bv_widget">
			<?php if($title) echo $before_title . $title . $after_title; ?>

			<div class="bv_police">
				<p class="bv_police_neighbourhood">You are in the <?php echo $xml->team_name; ?> policing neighbourhood</p>
				
				<dl class="bv_teams">
					<dt class="member ">Contact Details</dt>
					<?php 
						$i = 0; 
						foreach($xml->team_members->children() as $result) : 
							if ($i >= $instance['limit'] && $instance['limit'] > 0) { 
								break; 
							}
					?>
						
						<dd><?php echo $result->rank . ' ' . $result->name ?></dd>
					
					<?php 
						$i++;
						endforeach; 
					?>
				</dl>
				
				<p class="bv_contact"><a href="mailto:<?php echo $xml->team_email; ?>">E-mail</a></p>
				<p class="bv_contact"><a href="<?php echo $xml->team_url; ?>">Website</a></p>
				<p class="bv_contact"><?php echo $xml->team_phone; ?></p>
				
			</div>
			
			<div class="bv_council">
				<h4>Council Ward:</h4>
				<p class="bv_ward"><a href="<?php echo $ward_data->url ?>"><?php echo $ward_data->name ?></a></p>
				
				<?php
				
				// Councillors
				print $this->process_councillors($xml);
				
				// Priority Neighbourhoods
				if(isset($xml->pn_data->pn_code) && $instance['pn']) { 
					print $this->process_pn($xml);
				}
				
				// Meetings
				if($instance['meetings']) {
					print $this->process_meetings($xml);
				}
				
				?>
				
			</div>
			
		</div>
		
		<?php
		
		
		echo $after_widget;
		
    }

    /** @see WP_Widget::update */
    function update($new_instance, $old_instance) {				
        return $new_instance;
    }

    /** @see WP_Widget::form */
    function form($instance) {				
        $title = esc_attr($instance['title']);
        $limit = esc_attr($instance['limit']);
		$postcode = esc_attr($instance['postcode']);
		$pn = esc_attr($instance['pn']);
		$meetings = esc_attr($instance['meetings']);
		
        ?>
            <p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label></p>
            
            <p><label for="<?php echo $this->get_field_id('postcode'); ?>"><?php _e('Postcode:'); ?> <input class="widefat" id="<?php echo $this->get_field_id('postcode'); ?>" name="<?php echo $this->get_field_name('postcode'); ?>" type="text" value="<?php echo $postcode; ?>" /></label></p>
            
            <p><label for="<?php echo $this->get_field_id('limit'); ?>"><?php _e('Number of police contacts to show:'); ?> <input class="widefat" id="<?php echo $this->get_field_id('limit'); ?>" name="<?php echo $this->get_field_name('limit'); ?>" type="text" value="<?php echo $limit; ?>" /></label></p>
            
            <!-- // Optional stuff -->
            <p><label for="<?php echo $this->get_field_id('meetings'); ?>"><?php _e('Display meetings:'); ?> <input class="widefat" id="<?php echo $this->get_field_id('meetings'); ?>" name="<?php echo $this->get_field_name('meetings'); ?>" type="checkbox" value="1" <?php echo (!empty($meetings) ? 'checked="checked"' : ''); ?> /></label></p>
            
            <p><label for="<?php echo $this->get_field_id('pn'); ?>"><?php _e('Display priority neighbourhood info:'); ?> <input class="widefat" id="<?php echo $this->get_field_id('pn'); ?>" name="<?php echo $this->get_field_name('pn'); ?>" type="checkbox" value="1"<?php echo (!empty($pn) ? 'checked="checked"' : ''); ?> /></label></p>
            
        <?php
    }
	
	/**
	 * Will print the meetings info
	 */
	function process_meetings($xml) {
		$out = '<dl>';
		$out .= '<dt class="bv_meetings" >Meetings</dt>';
		
		$mArray = $this->ward_data->meetings->children();
		if(empty($mArray['meeting'])) return;
		
		$mArray = (array)$mArray;
		$mArray = array_reverse($mArray['meeting']);
		$m = 0;
		foreach($mArray as $meeting) {
			if($m >= 5) break;
			$out .= '<dd><a href="' . $meeting->url . '">' . $meeting->title . '</a><br />';
			$out .= 'at ' . $meeting->venue . '<br />';
			$temp = (array)$meeting;
			$out .= 'on ' . $temp['formatted-date'];
			$out .= '</dd>';
			$m++;
		}
		$out .= '</dl>';
		
		return $out;
	}
	
	function process_pn($xml) {
		$out = '<dl class="bv_priority">' . "\n";
		$out .= '<dt>You live in a Priority Neighbourhood. Your ward officers are:</dt>' . "\n";
		
		foreach($xml->ward_officers->children() as $officer) {
			$out .= '<dd class="ward_officer">' . $officer->type . ' ' . $officer->name . ' - ' . $officer->phone . '</dd>';
		}
		
		$out .= '</dl>';
		
		return $out;
	}
	
	function process_councillors($xml) {
		$out = '<dl class="bv_councillors">';
		$out .= '<dt>Your councillors are:</dt>';
					
		// Councillors
		foreach($this->ward_data->members->member as $member) {
			$member = (array)$member;
			
			$out .= '<dd>' . $member['first-name'] . ' ' . $member['last-name'] . ' (' . substr($member['party'], 0, 3) . ') ';
			$out .= '<a href="javascript:void(0)" class="councillor-contact" id="member-' . $member['id'] . '" onClick="showstuff(\'member-data-' . $member['id'] . '\');">contact</a>';
			$out .= '<div class="councillor-contact-data" id="member-data-' . $member['id'] .'" style="display:none;">';
			$out .= '<ul>';
			$out .= '<li>E-mail: ' . $member['email'] . '</li>';
			$out .= '<li>Phone: ' . $member['telephone'] . '</li>';
			$out .= '<li><a href="' . $member['url'] . '">Website</a></li>';
			$out .= '</ul></div>';
			$out .= '</dd>';
		}	

		$out .= '</dl>';
		
		return $out;
	}
	
	function bv_add_scripts($args) {
	?>
	
	<script type="text/javascript">
			
		function showstuff(boxid){
	   		document.getElementById(boxid).style.visibility="visible";
	   		document.getElementById(boxid).style.display="block";
		}
	
		function hidestuff(boxid){
		   document.getElementById(boxid).style.visibility="hidden";
		   document.getElementById(boxid).style.display="none";
		}
	
	</script>
			
	<?php
	}
}

add_action('widgets_init', create_function('', 'return register_widget("BeVocalWidget");'));

