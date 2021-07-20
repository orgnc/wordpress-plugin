<?php


namespace Empire;

/**
 * Manages the ads.txt data and presentation
 *
 * @package Empire
 */
class AdsTxt {

    /**
     * @var Empire
     */
    private $empire;

    public function __construct( Empire $empire ) {
        $this->empire = $empire;

        add_action( 'init', array( $this, 'show' ) );
    }

    public function show() {
        if ( isset( $_SERVER ) && $_SERVER['REQUEST_URI'] === '/ads.txt' ) {
            $enabled = get_option( 'empire::enabled' );

            if ( $enabled ) {
                $adsTxt = get_option( 'empire::ads_txt' );
                header( 'content-type: text/plain; charset=UTF-8' );
                header( 'cache-control: public, max-age=86400' );
                echo $adsTxt;
                exit;
            }
        }
    }

    /**
     * Called to set up our initial ads.txt on plugin activation
     */
    public function activate() {
        update_option( 'empire::ads_txt', $this->defaultAdsTxt() );
    }

    /**
     * Default ads.txt as of 10/19/2020
     *
     * @return string
     */
    public function defaultAdsTxt() {
        return <<<EOF
# Additional
google.com, pub-9629478125243535, DIRECT, f08c47fec0942fa0
districtm.io, 101880, DIRECT, 3fd707be9c4527c3
appnexus.com, 1908, RESELLER, f5ab79cb980f11d1
appnexus.com, 10890, DIRECT
indexexchange.com, 189992, DIRECT

# Amazon APS
aps.amazon.com,49cdee1c-072d-4765-8313-7c45826cbedc,DIRECT
pubmatic.com,157150,RESELLER,5d62403b186f2ace
openx.com,540191398,RESELLER,6a698e2ec38604c6
rubiconproject.com,18020,RESELLER,0bfd66d529a55807
appnexus.com,1908,RESELLER,f5ab79cb980f11d1
adtech.com,12068,RESELLER,e1a5b5b6e3255540
ad-generation.jp,12474,RESELLER
districtm.io,100962,RESELLER,3fd707be9c4527c3
yahoo.com,56671,DIRECT,e1a5b5b6e3255540
appnexus.com,3663,RESELLER,f5ab79cb980f11d1
rhythmone.com,1654642120,RESELLER,a670c89d4a324e47
yahoo.com,55029,RESELLER,e1a5b5b6e3255540

# SOVRN
sovrn.com, 268983, DIRECT, fafdf38b16bf6b2b
lijit.com, 268983, DIRECT, fafdf38b16bf6b2b
lijit.com, 268983-eb, DIRECT, fafdf38b16bf6b2b
appnexus.com, 1360, RESELLER, f5ab79cb980f11d1
gumgum.com, 11645, RESELLER, ffdef49475d318a9
openx.com, 538959099, RESELLER, 6a698e2ec38604c6
openx.com, 539924617, RESELLER, 6a698e2ec38604c6
pubmatic.com, 137711, RESELLER, 5d62403b186f2ace
pubmatic.com, 156212, RESELLER, 5d62403b186f2ace
pubmatic.com, 156700, RESELLER, 5d62403b186f2ace
rubiconproject.com, 17960, RESELLER, 0bfd66d529a55807

# Rubicon
rubiconproject.com, 21048, DIRECT, 0bfd66d529a55807
rubiconproject.com, 17960, RESELLER, 0bfd66d529a55807

# Rubicon EB
rubiconproject.com, 21050, DIRECT, 0bfd66d529a55807

# EB
openx.com, 540717786, DIRECT, 6a698e2ec38604c6
openx.com, 540799151, DIRECT, 6a698e2ec38604c6
EMXDGT.com, 1218, DIRECT, 1e1d41537f7cad7f
appnexus.com, 1356, RESELLER, f5ab79cb980f11d1
media.net, 8CUR5WHJ6, DIRECT
rhythmone.com, 2182297330, DIRECT, a670c89d4a324e47
sonobi.com, a751b1a35e, DIRECT, d1a215d9eb5aee9e
indexexchange.com, 190179, DIRECT, 50b1c356f2c5c8fc

# RhythmOne
rhythmone.com, 581030648, DIRECT, a670c89d4a324e47
video.unrulymedia.com, 581030648,DIRECT

# AOL / Verizon Media
adtech.com, 11700, DIRECT, e1a5b5b6e3255540
aol.com, 54459, DIRECT, e1a5b5b6e3255540
indexexchange.com, 175407, RESELLER, 50b1c356f2c5c8fc
openx.com, 537143344, RESELLER
pubmatic.com, 156078, RESELLER, 5d62403b186f2ace
contextweb.com, 558299, RESELLER, 89ff185a4c4e857c
indexexchange.com, 184110, RESELLER, 50b1c356f2c5c8fc
openx.com, 539959224, RESELLER, 6a698e2ec38604c6
pubmatic.com, 156138, RESELLER
yahoo.com, 1827588, DIRECT, e1a5b5b6e3255540
yahoo.com, 54459, DIRECT, e1a5b5b6e3255540
yahoo.com, 55386, DIRECT, e1a5b5b6e3255540
yahoo.com, 56038, DIRECT

# GumGum
gumgum.com, 13842, DIRECT, ffdef49475d318a9
33across.com, 0013300001r0t9mAAA, RESELLER
appnexus.com, 1001, RESELLER, f5ab79cb980f11d1
appnexus.com, 1942, RESELLER, f5ab79cb980f11d1
appnexus.com, 2758, RESELLER, f5ab79cb980f11d1
appnexus.com, 3135, RESELLER, f5ab79cb980f11d1
appnexus.com, 7597, RESELLER, f5ab79cb980f11d1
appnexus.com, 10239, RESELLER, f5ab79cb980f11d1
bidtellect.com, 1407, RESELLER, 1c34aa2d85d45e93
contextweb.com, 558355, RESELLER
emxdgt.com, 326, RESELLER, 1e1d41537f7cad7f
google.com, pub-9557089510405422, RESELLER, f08c47fec0942fa0
google.com, pub-3848273848634341, RESELLER, f08c47fec0942fa0
openx.com, 537120563, RESELLER, 6a698e2ec38604c6
openx.com, 537149485, RESELLER, 6a698e2ec38604c6
openx.com, 539392223, RESELLER, 6a698e2ec38604c6
pubmatic.com, 156423, RESELLER, 5d62403b186f2ace
pubmatic.com, 157897, RESELLER, 5d62403b186f2ace
rhythmone.com, 78519861, RESELLER
rhythmone.com, 2439829435, RESELLER, a670c89d4a324e47
rubiconproject.com, 16414, RESELLER, 0bfd66d529a55807
spotx.tv, 147949, RESELLER, 7842df1d2fe2db34
spotxchange.com, 147949, RESELLER, 7842df1d2fe2db34

# Sonobi
sonobi.com, f664fbdf1f, DIRECT, d1a215d9eb5aee9e
rhythmone.com, 1059622079, RESELLER, a670c89d4a324e47
contextweb.com, 560606, RESELLER, 89ff185a4c4e857c

# Conversant
conversantmedia.com, 100043, DIRECT, 03113cd04947736d
appnexus.com, 4052, RESELLER
openx.com, 540031703, RESELLER, 6a698e2ec38604c6
contextweb.com, 561998, RESELLER, 89ff185a4c4e857c
pubmatic.com, 158100, RESELLER, 5d62403b186f2ace

# Video Test
google.com, pub-4968145218643279, DIRECT, f08c47fec0942fa0

# Kargo
kargo.com, 8514, Direct
indexexchange.com, 184081 , reseller
appnexus.com, 8173, RESELLER
contextweb.com, 562001, RESELLER, 89ff185a4c4e857c
lkqd.net, 605, RESELLER, 59c49fa9598a0117
rubiconproject.com, 11864, RESELLER, 0bfd66d529a55807
EOF;
    }
}
