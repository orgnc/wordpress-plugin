<?php

namespace Empire;

class PostEditor {

    /**
     * @var Empire
     */
    private $empire;

    public function __construct( Empire $empire ) {
        $this->empire = $empire;

        add_action( 'add_meta_boxes', array( $this, 'addMetabox' ) );
        add_action( 'save_post', array( $this, 'savePostSettings' ), 10, 3 );
    }

    public function addMetabox() {
        $enabled = get_option( 'empire::enabled' );
        $screens = array( 'post', 'page' );

        if ( $enabled ) {
            add_meta_box( 'empire_metabox', 'Empire ', array( $this, 'drawEmpireMetabox' ), $screens, 'side' );
        }
    }

    public function drawEmpireMetabox() {
        global $post;

        $empire_link = get_post_meta( $post->ID, 'empire_link', true );

        $regions = get_option( 'empire::all_regions', array() );
        $vendors = get_option( 'empire::all_vendors', array() );
        $allow_manual_tags = get_option( 'empire::allow_manual_tags' );
        $article_tags = array();

        foreach ( $vendors as $vendor ) {
            if ( $vendor['enabled'] ) {
                $vendor['regions'] = array();
                $vendor['in_use'] = false;
                $article_tags[ $vendor['code'] ] = $vendor;

                foreach ( $regions as $idx => $region ) {
                    if ( $region['enabled'] ) {
                        $key = 'empire::tag_' . $vendor['code'] . '_' . $region['code'];
                        $tag = get_post_meta( $post->ID, $key, true );
                        $region['tag'] = $tag;

                        if ( $tag ) {
                            $article_tags[ $vendor['code'] ]['in_use'] = true;
                        }

                        $article_tags[ $vendor['code'] ]['regions'][ $region['code'] ] = $region;
                    }
                }
            }
        }

        wp_nonce_field( __FILE__, 'custom-sidebar' );
        ?>
        <div class="empire-block">
            <?php if ( $empire_link ) { ?>
            <p><strong>Tracked:</strong> yes</p>
            <p><strong>Analytics:</strong> <a target="_blank" href="<?php echo $empire_link; ?>">check Empire</a></p>
            <?php } else { ?>
            <p><strong>Tracked: </strong> no</p>
            <?php } ?>
            <?php
            foreach ( $article_tags as $vendor_code => $vendor ) {
                if ( $vendor['in_use'] || $allow_manual_tags ) {
                    echo '<p><strong>' . $vendor['name'] . '</strong></p>';
                    if ( count( $vendor['regions'] ) == 0 && ! $allow_manual_tags ) {
                        echo '<p>No Custom Tags Set</p>';
                    } else {
                        echo '<ul>';
                        foreach ( $vendor['regions'] as $region_code => $region ) {
                            echo '<li>';
                            if ( $allow_manual_tags ) {
                                $key = 'empire_tag_' . $vendor['code'] . '_' . $region_code;
                                echo '<label>' . $region['code'] . ': <input type="text" name="' . $key . '" value="' . htmlspecialchars( $region['tag'] ) . '" /></label>';
                            } else {
                                echo $region['code'] . ': ' . $region['tag'];
                            }
                            echo '</li>';
                        }
                        echo '</ul>';
                    }
                }
            }
            ?>
        </div>
        <?php
    }

    public function savePostSettings( $postID, $post, $isUpdate ) {
        foreach ( $_POST as $key => $value ) {
            if ( substr( $key, 0, 13 ) == 'empire_tag_' ) {
                $parts = explode( '_', $key, 4 );
                $vendorCode = $parts[2];
                $regionCode = $parts[3];

                $key = 'empire::tag_' . $vendorCode . '_' . $regionCode;
                update_post_meta( $postID, $key, $value );
            }
        }
    }
}
