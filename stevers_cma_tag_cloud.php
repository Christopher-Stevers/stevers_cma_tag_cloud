<?php
/**
*Plugin Name: Christopher Stever's Plugin for cma tag cloud example.
*Description: A plugin for a tag shortcode that points too the cma tags.
*/



function wp_generate_custom_tag_cloud( $tags, $args = '' ) {
    $defaults = array(
        'smallest'                   => 12,
        'largest'                    => 30,
        'unit'                       => 'pt',
        'number'                     => 0,
        'format'                     => 'flat',
        'separator'                  => "\n",
        'orderby'                    => 'name',
        'order'                      => 'ASC',
        'topic_count_text'           => null,
        'topic_count_text_callback'  => null,
        'topic_count_scale_callback' => 'default_topic_count_scale',
        'filter'                     => 1,
        'show_count'                 => 0,
    );
 
    $args = wp_parse_args( $args, $defaults );
 
    $return = ( 'array' === $args['format'] ) ? array() : '';
 
    if ( empty( $tags ) ) {
        return $return;
    }
 
    // Juggle topic counts.
    if ( isset( $args['topic_count_text'] ) ) {
        // First look for nooped plural support via topic_count_text.
        $translate_nooped_plural = $args['topic_count_text'];
    } elseif ( ! empty( $args['topic_count_text_callback'] ) ) {
        // Look for the alternative callback style. Ignore the previous default.
        if ( 'default_topic_count_text' === $args['topic_count_text_callback'] ) {
            /* translators: %s: Number of items (tags). */
            $translate_nooped_plural = _n_noop( '%s item', '%s items' );
        } else {
            $translate_nooped_plural = false;
        }
    } elseif ( isset( $args['single_text'] ) && isset( $args['multiple_text'] ) ) {
        // If no callback exists, look for the old-style single_text and multiple_text arguments.
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralSingle,WordPress.WP.I18n.NonSingularStringLiteralPlural
        $translate_nooped_plural = _n_noop( $args['single_text'], $args['multiple_text'] );
    } else {
        // This is the default for when no callback, plural, or argument is passed in.
        /* translators: %s: Number of items (tags). */
        $translate_nooped_plural = _n_noop( '%s item', '%s items' );
    }
 
    /**
     * Filters how the items in a tag cloud are sorted.
     *
     * @since 2.8.0
     *
     * @param WP_Term[] $tags Ordered array of terms.
     * @param array     $args An array of tag cloud arguments.
     */
    $tags_sorted = apply_filters( 'tag_cloud_sort', $tags, $args );
    if ( empty( $tags_sorted ) ) {
        return $return;
    }
 
    if ( $tags_sorted !== $tags ) {
        $tags = $tags_sorted;
        unset( $tags_sorted );
    } else {
        if ( 'RAND' === $args['order'] ) {
            shuffle( $tags );
        } else {
            // SQL cannot save you; this is a second (potentially different) sort on a subset of data.
            if ( 'name' === $args['orderby'] ) {
                uasort( $tags, '_wp_object_name_sort_cb' );
            } else {
                uasort( $tags, '_wp_object_count_sort_cb' );
            }
 
            if ( 'DESC' === $args['order'] ) {
                $tags = array_reverse( $tags, true );
            }
        }
    }
 
    if ( $args['number'] > 0 ) {
        $tags = array_slice( $tags, 0, $args['number'] );
    }
 
    $counts      = array();
    $real_counts = array(); // For the alt tag.
    foreach ( (array) $tags as $key => $tag ) {
        $real_counts[ $key ] = $tag->count;
        $counts[ $key ]      = call_user_func( $args['topic_count_scale_callback'], $tag->count );
    }
 
    $min_count = min( $counts );
    $spread    = max( $counts ) - $min_count;
    if ( $spread <= 0 ) {
        $spread = 1;
    }
    $font_spread = $args['largest'] - $args['smallest'];
    if ( $font_spread < 0 ) {
        $font_spread = 1;
    }
    $font_step = $font_spread / $spread;
 
    $aria_label = false;
    /*
     * Determine whether to output an 'aria-label' attribute with the tag name and count.
     * When tags have a different font size, they visually convey an important information
     * that should be available to assistive technologies too. On the other hand, sometimes
     * themes set up the Tag Cloud to display all tags with the same font size (setting
     * the 'smallest' and 'largest' arguments to the same value).
     * In order to always serve the same content to all users, the 'aria-label' gets printed out:
     * - when tags have a different size
     * - when the tag count is displayed (for example when users check the checkbox in the
     *   Tag Cloud widget), regardless of the tags font size
     */
    if ( $args['show_count'] || 0 !== $font_spread ) {
        $aria_label = true;
    }
 
    // Assemble the data that will be used to generate the tag cloud markup.
    $tags_data = array();
    foreach ( $tags as $key => $tag ) {
        $tag_id = isset( $tag->id ) ? $tag->id : $key;
 
        $count      = $counts[ $key ];
        $real_count = $real_counts[ $key ];
 
        if ( $translate_nooped_plural ) {
            $formatted_count = sprintf( translate_nooped_plural( $translate_nooped_plural, $real_count ), number_format_i18n( $real_count ) );
        } else {
            $formatted_count = call_user_func( $args['topic_count_text_callback'], $real_count, $tag, $args );
        }
 
        $tags_data[] = array(
            'id'              => $tag_id,
            'url'             => ( '#' !== $tag->link ) ? $tag->link : '#',
            'role'            => ( '#' !== $tag->link ) ? '' : ' role="button"',
            'name'            => $tag->name,
            'formatted_count' => $formatted_count,
            'slug'            => $tag->slug,
            'real_count'      => $real_count,
            'class'           => 'tag-cloud-link tag-link-' . $tag_id,
            'font_size'       => $args['smallest'] + ( $count - $min_count ) * $font_step,
            'aria_label'      => $aria_label ? sprintf( ' aria-label="%1$s (%2$s)"', esc_attr( $tag->name ), esc_attr( $formatted_count ) ) : '',
            'show_count'      => $args['show_count'] ? '<span class="tag-link-count"> (' . $real_count . ')</span>' : '',
        );
    }
 
    /**
     * Filters the data used to generate the tag cloud.
     *
     * @since 4.3.0
     *
     * @param array[] $tags_data An array of term data arrays for terms used to generate the tag cloud.
     */
    $tags_data = apply_filters( 'wp_generate_tag_cloud_data', $tags_data );
 
    $a = array();
 
    // Generate the output links array.
    foreach ( $tags_data as $key => $tag_data ) {
        $class = $tag_data['class'] . ' tag-link-position-' . ( $key + 1 );
        $a[]   = sprintf(
            '<a href="/?post_type=cma_thread&cmatag=%6$s%7$s" class="%3$s" style="font-size: %4$s;"%5$s>%6$s%7$s</a>',
            esc_url( $tag_data['url'] ),
            $tag_data['role'],
            esc_attr( $class ),
            esc_attr( str_replace( ',', '.', $tag_data['font_size'] ) . $args['unit'] ),
            $tag_data['aria_label'],
            esc_html( $tag_data['name'] ),
            $tag_data['show_count']
        );
    }
 
    switch ( $args['format'] ) {
        case 'array':
            $return =& $a;
            break;
        case 'list':
            /*
             * Force role="list", as some browsers (sic: Safari 10) don't expose to assistive
             * technologies the default role when the list is styled with `list-style: none`.
             * Note: this is redundant but doesn't harm.
             */
            $return  = "<ul class='wp-tag-cloud' role='list'>\n\t<li>";
            $return .= implode( "</li>\n\t<li>", $a );
            $return .= "</li>\n</ul>\n";
            break;
        default:
            $return = implode( $args['separator'], $a );
            break;
    }
 
    if ( $args['filter'] ) {
        /**
         * Filters the generated output of a tag cloud.
         *
         * The filter is only evaluated if a true value is passed
         * to the $filter argument in wp_generate_tag_cloud().
         *
         * @since 2.3.0
         *
         * @see wp_generate_tag_cloud()
         *
         * @param string[]|string $return String containing the generated HTML tag cloud output
         *                                or an array of tag links if the 'format' argument
         *                                equals 'array'.
         * @param WP_Term[]       $tags   An array of terms used in the tag cloud.
         * @param array           $args   An array of wp_generate_tag_cloud() arguments.
         */
        return apply_filters( 'wp_generate_tag_cloud', $return, $tags, $args );
    } else {
        return $return;
    }
}


function stevers_shortcode_for_tag_cloud()
{
 $defaults = array(
        'smallest'   => 8,
        'largest'    => 22,
        'unit'       => 'pt',
        'number'     => 45,
        'format'     => 'flat',
        'separator'  => "\n",
        'orderby'    => 'name',
        'order'      => 'ASC',
        'exclude'    => '',
        'include'    => '',
        'link'       => 'view',
        'taxonomy'   => 'post_tag',
        'post_type'  => '',
        'echo'       => true,
        'show_count' => 0,
    );
 
    $args = wp_parse_args( $args, $defaults );
 
    $tags = get_terms(
        array_merge(
            $args,
            array(
                'orderby' => 'count',
                'order'   => 'DESC',
            )
        )
    ); // Always query top tags.
 
    if ( empty( $tags ) || is_wp_error( $tags ) ) {
        return;
    }
 
    foreach ( $tags as $key => $tag ) {
        if ( 'edit' === $args['link'] ) {
            $link = get_edit_term_link( $tag->term_id, $tag->taxonomy, $args['post_type'] );
        } else {
            $link = get_term_link( (int) $tag->term_id, $tag->taxonomy );
        }
 
        if ( is_wp_error( $link ) ) {
            return;
        }
 
        $tags[ $key ]->link = $link;
        $tags[ $key ]->id   = $tag->term_id;
    }
 
    // Here's where those top tags get sorted according to $args.
    $return = wp_generate_custom_tag_cloud( $tags, $args );
 
    /**
     * Filters the tag cloud output.
     *
     * @since 2.3.0
     *
     * @param string|string[] $return Tag cloud as a string or an array, depending on 'format' argument.
     * @param array           $args   An array of tag cloud arguments. See wp_tag_cloud()
     *                                for information on accepted arguments.
     */
    $return = apply_filters( 'wp_tag_cloud', $return, $args );
 
    if ( 'array' === $args['format'] || empty( $args['echo'] ) ) {
        return $return;
    }
 
    return $return;
}

function wpb_hook_javascript_footer() {
    ?>
        <script>
			
	
			
			
			
			
			
			const questionInput = document.querySelector("input[name='thread_title']");
const descriptionInput = document.querySelector("textarea[name='thread_content']");

function addWordCount(elem, id) {

    function inputHandler(e) {

        if (elem) {
            if (document.getElementById(id)) document.getElementById(id).remove();
            const questionExplainer = document.createElement("div");
			const regex=/[^.]\S+/g;
			
			const newText=(elem.textContent) ? elem.textContent : e.target.value;
			
			const wordArr=newText.match(regex)
            questionExplainer.innerText = wordArr.length+" words"
            questionExplainer.id = id;
            
			  if (elem.nodeName === 'INPUT' || elem.nodeName === 'TEXTAREA')elem.insertAdjacentElement('afterend', questionExplainer);
			  else{
				  if(document.querySelector('.cma-question-date'))
				  {document.querySelector('.cma-question-date').insertAdjacentElement('afterend', questionExplainer);}
			  }
        }
    }

    if (elem) {
        
        if (elem.nodeName === 'INPUT' || elem.nodeName === 'TEXTAREA') {
            elem.addEventListener("input", inputHandler);
           
        }
        else {
            inputHandler(elem);
        }
    }

            

};

const onloadText=document.querySelector("div.cma-question-body-content") ? document.querySelector("div.cma-question-body-content").firstElementChild : null;
addWordCount(onloadText, "questionTextId");
addWordCount(questionInput, "questionInputId");
addWordCount(descriptionInput, "descriptionInputId");
			
			
			
			
					//THIRD PARTY from https://gist.github.com/icodejs/3183154
var open = window.XMLHttpRequest.prototype.open,
    send = window.XMLHttpRequest.prototype.send,
    onReadyStateChange;

function openReplacement(method, url, async, user, password) {
    var syncMode = async !== false ? 'async' : 'sync';
    
	
	
    return open.apply(this, arguments);
}

function sendReplacement(data) {
    
    if(this.onreadystatechange) {
        this._onreadystatechange = this.onreadystatechange;
    }
    this.onreadystatechange = onReadyStateChangeReplacement;

    return send.apply(this, arguments);
}

function onReadyStateChangeReplacement() {
		const questionText = document.querySelector("div.cma-question-body-content") ? document.querySelector("div.cma-question-body-content").firstElementChild : null;
addWordCount(questionText, "questionTextId");
	
   const newElem=document.createElement("a");
   newElem.className="google-me";
			const multiSpaceRegex= /\s+/g;
	
	if(document.querySelector('.cma-thread-title-h1')){
		console.log("yeet");
	const newString=document.querySelector('.cma-thread-title-h1').innerText.replace(multiSpaceRegex,"+");
   newElem.href=`https://www.google.com/search?q=${newString}`;
	newElem.innerText="Google Answer";
   if(document.querySelector("table")&& !document.querySelector(".google-me")){document.querySelector("table").insertAdjacentElement('afterend', newElem);} 
	console.log(newElem);
	
	}
    if(this._onreadystatechange) {
        return this._onreadystatechange.apply(this, arguments);
    }
}

window.XMLHttpRequest.prototype.open = openReplacement;
window.XMLHttpRequest.prototype.send = sendReplacement;
			
			
			
			
const processText=(elem)=>{
	const eleminator=elem;
	const spaceRegex=/\s/g
	const characterString=elem.firstElementChild.innerText.replace(spaceRegex,"");
elem.innerHTML=`<div class="cma-thread-summary-right"><div class="cma-thread-posted">${characterString.length} characters</div></div>`;
	
}			
			
listOfContent=document.querySelectorAll(".cma-thread-content");
			

			
listOfContent.forEach(elem=>processText(elem));
function  clickOnLink(e){
const headingText=e.target.innerText;
	 document.getElementById('questions-field-heading').remove();
	
   const newElem=document.createElement("a");
   newElem.class="google";
   newElem.href="https://www.google.com/search?q=trump+putin"
	newElem.innerText="oogle me";
   if(document.querySelector("table")){document.querySelector("table").insertAdjacentElement(newElem);
	console.log(newElem);}

}
			function setUp(e){
			e.addEventListener("click", clickOnLink)}
const nameHeadings=document.querySelectorAll(".cma-thread-title > a");
	
			nameHeadings.forEach(e=>setUp(e));

const questionTitle=document.querySelector(
".cma_thread>.main_title"
);
		if(questionTitle){
 const newElem=document.createElement("a");
			const regex= /\s+/g
			const newString=questionTitle.innerText.replace(regex, "+");
   newElem.href=`https://www.google.com/search?q=${newString}`;
   newElem.className="google-me";
	newElem.innerText="Google Answer";
   document.querySelector("table").insertAdjacentElement('afterend', newElem);
	console.log(newElem);
}
        </script>
    <?php
}
add_action('wp_footer', 'wpb_hook_javascript_footer');



add_shortcode('stevers_cma_tag_cloud','stevers_shortcode_for_tag_cloud');
?>
