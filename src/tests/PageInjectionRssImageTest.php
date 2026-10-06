<?php

namespace {
    if ( ! function_exists( 'has_post_thumbnail' ) ) {
        function has_post_thumbnail() {
            return \Organic\PageInjectionRssImageTestState::$hasPostThumbnail;
        }
    }

    if ( ! function_exists( 'get_the_post_thumbnail_url' ) ) {
        function get_the_post_thumbnail_url( $post = null, $size = 'thumbnail' ) {
            return \Organic\PageInjectionRssImageTestState::$thumbnailUrl;
        }
    }

    if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
        function get_post_thumbnail_id( $post = null ) {
            return \Organic\PageInjectionRssImageTestState::$thumbnailId;
        }
    }

    if ( ! function_exists( 'wp_get_attachment_caption' ) ) {
        function wp_get_attachment_caption( $attachment_id = 0 ) {
            return \Organic\PageInjectionRssImageTestState::$caption;
        }
    }

    if ( ! function_exists( 'get_field' ) ) {
        function get_field( $selector, $post_id = false, $format_value = true ) {
            return \Organic\PageInjectionRssImageTestState::$credit;
        }
    }

    if ( ! function_exists( 'esc_url' ) ) {
        function esc_url( $url ) {
            return $url;
        }
    }

    if ( ! function_exists( 'esc_html' ) ) {
        function esc_html( $text ) {
            return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8', true );
        }
    }
}

namespace Organic {
    use PHPUnit\Framework\TestCase;

    class PageInjectionRssImageTestState {
        public static $hasPostThumbnail = true;
        public static $thumbnailUrl = '';
        public static $thumbnailId = 42;
        public static $caption = '';
        public static $credit = '';

        public static function reset() {
            self::$hasPostThumbnail = true;
            self::$thumbnailUrl = '';
            self::$thumbnailId = 42;
            self::$caption = '';
            self::$credit = '';
        }
    }

    class PageInjectionRssImageTest extends TestCase {
        /**
         * @var PageInjection
         */
        private $injection;

        public function setUp(): void {
            PageInjectionRssImageTestState::reset();

            $reflection = new \ReflectionClass( PageInjection::class );
            $this->injection = $reflection->newInstanceWithoutConstructor();
        }

        private function renderRssImage() {
            ob_start();
            $this->injection->injectRssImage();
            return (string) ob_get_clean();
        }

        public function testRssImageCombinesCaptionAndCreditInMediaTitle() {
            PageInjectionRssImageTestState::$thumbnailUrl = 'https://www.example.test/uploads/shade.jpg';
            PageInjectionRssImageTestState::$caption = 'A shaded coral.';
            PageInjectionRssImageTestState::$credit = 'Karen Neely';

            $this->assertSame(
                '<media:content url="https://www.example.test/uploads/shade.jpg" medium="image">'
                    . '<media:title>A shaded coral. (Credit: Karen Neely)</media:title>'
                    . '</media:content>',
                $this->renderRssImage()
            );
        }

        public function testRssImageKeepsCaptionWhenCreditIsTypedInManually() {
            PageInjectionRssImageTestState::$thumbnailUrl = 'https://www.example.test/uploads/shade.jpg';
            PageInjectionRssImageTestState::$caption = 'A shaded coral. (CREDIT: Karen Neely)';
            PageInjectionRssImageTestState::$credit = 'Karen Neely';

            $this->assertSame(
                '<media:content url="https://www.example.test/uploads/shade.jpg" medium="image">'
                    . '<media:title>A shaded coral. (CREDIT: Karen Neely)</media:title>'
                    . '</media:content>',
                $this->renderRssImage()
            );
        }

        public function testRssImageDoesNotDuplicateManuallyTypedCreditFromField() {
            PageInjectionRssImageTestState::$thumbnailUrl = 'https://www.example.test/uploads/shade.jpg';
            PageInjectionRssImageTestState::$caption = 'A view of CHIME at night. (CREDIT: CHIME collaboration)';
            PageInjectionRssImageTestState::$credit = 'CHIME Collaboration';

            $this->assertSame(
                '<media:content url="https://www.example.test/uploads/shade.jpg" medium="image">'
                    . '<media:title>A view of CHIME at night. (CREDIT: CHIME collaboration)</media:title>'
                    . '</media:content>',
                $this->renderRssImage()
            );
        }

        public function testRssImageRendersCaptionOnlyWithoutCredit() {
            PageInjectionRssImageTestState::$thumbnailUrl = 'https://www.example.test/uploads/shade.jpg';
            PageInjectionRssImageTestState::$caption = 'A shaded coral.';
            PageInjectionRssImageTestState::$credit = '';

            $this->assertSame(
                '<media:content url="https://www.example.test/uploads/shade.jpg" medium="image">'
                    . '<media:title>A shaded coral.</media:title>'
                    . '</media:content>',
                $this->renderRssImage()
            );
        }

        public function testRssImageOmitsMediaTitleWithoutCaptionAndCredit() {
            PageInjectionRssImageTestState::$thumbnailUrl = 'https://www.example.test/uploads/shade.jpg';

            $this->assertSame(
                '<media:content url="https://www.example.test/uploads/shade.jpg" medium="image"></media:content>',
                $this->renderRssImage()
            );
        }

        public function testRssImageOutputsNothingWithoutThumbnail() {
            PageInjectionRssImageTestState::$hasPostThumbnail = false;

            $this->assertSame( '', $this->renderRssImage() );
        }

        public function testRssImageEscapesMediaTitle() {
            PageInjectionRssImageTestState::$thumbnailUrl = 'https://www.example.test/uploads/shade.jpg';
            PageInjectionRssImageTestState::$caption = 'A shark & its reef';
            PageInjectionRssImageTestState::$credit = 'O\'Brien';

            $this->assertSame(
                '<media:content url="https://www.example.test/uploads/shade.jpg" medium="image">'
                    . '<media:title>A shark &amp; its reef (Credit: O&#039;Brien)</media:title>'
                    . '</media:content>',
                $this->renderRssImage()
            );
        }
    }
}
