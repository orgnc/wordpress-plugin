<?php

namespace Empire;

/**
 * Determine whether this is an AMP response.
 *
 * Note that this must only be called after the parse_query action.
 *
 * @return bool Is AMP endpoint (and AMP plugin is active).
 */
function empire_is_amp()
{
    // naive implementation due to we're in mu-plugin
    return isset($_GET['amp']);
}
