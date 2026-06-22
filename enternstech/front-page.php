<?php
/**
 * Front Page Template
 *
 * Serves the Design Canvas bundled site as the homepage.
 *
 * @package EnternsTech
 */

// ══════════════════════════════════════════════════════════════════════════════
// EDITABLE: Recently-placed scenarios shown in the hero floating cards.
//
// Each entry cycles through the placement badge (top-right card) and the
// secondary info card. Add as many rows as you like; the hero badge will
// rotate through them every ~3 seconds.
//
//   'role'     — job title shown in the floating badge
//   'weeks'    — training duration in weeks
//   'company'  — employer name (shown in the secondary card)
//   'initials' — 2-letter avatar label inside the badge dot
// ══════════════════════════════════════════════════════════════════════════════
$et_placements = array(
	array( 'role' => 'Data Scientist',       'weeks' => 11, 'company' => 'Infosys',   'initials' => 'DS' ),
	array( 'role' => 'Java Developer',        'weeks' =>  9, 'company' => 'TCS',       'initials' => 'JD' ),
	array( 'role' => 'DevOps Engineer',       'weeks' => 13, 'company' => 'Wipro',     'initials' => 'DO' ),
	array( 'role' => 'Business Analyst',      'weeks' => 15, 'company' => 'Accenture', 'initials' => 'BA' ),
	array( 'role' => 'Cybersecurity Analyst', 'weeks' => 12, 'company' => 'HCL',       'initials' => 'CA' ),
	array( 'role' => 'Full Stack Developer',  'weeks' => 10, 'company' => 'Capgemini', 'initials' => 'FS' ),
);

while ( ob_get_level() ) {
	ob_end_clean();
}

$bundled = get_template_directory() . '/static/index.html';

if ( file_exists( $bundled ) ) {
	// No caching — bypass browser cache, WordPress cache, and LiteSpeed server cache.
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'Cache-Control: no-cache, no-store, must-revalidate' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );
	header( 'X-LiteSpeed-Cache-Control: no-cache' );

	$html = file_get_contents( $bundled );

	// ── PayPal config ──────────────────────────────────────────────────────────
	$client  = defined( 'ENTERNSTECH_PAYPAL_CLIENT' ) ? ENTERNSTECH_PAYPAL_CLIENT : '';
	$env     = function_exists( 'enternstech_paypal_env' ) ? enternstech_paypal_env() : 'sandbox';
	$create  = esc_url_raw( rest_url( 'enternstech/v1/paypal/create' ) );
	$capture = esc_url_raw( rest_url( 'enternstech/v1/paypal/capture' ) );

	$inject = '<script>window.ENTERNSTECH_PAYPAL=' . wp_json_encode( array(
		'clientId'   => $client,
		'env'        => $env,
		'createUrl'  => $create,
		'captureUrl' => $capture,
	) ) . ';</script>';

	if ( $client ) {
		$inject .= '<script src="https://www.paypal.com/sdk/js?client-id=' . rawurlencode( $client )
			. '&currency=USD" data-namespace="enternsPayPal"></script>';
	}

	// ── Stub Design-Canvas component methods called as globals ─────────────────
	$inject .= '<script>window.closeAdmin=function(){};window.openAdmin=function(){};</script>';

	// ── Base CSS ───────────────────────────────────────────────────────────────
	// #__bundler_loading  — "Unpacking…" text badge — hidden immediately.
	// #__bundler_thumbnail — full-screen SVG overlay (z-index 9999) that covers
	//   the real floatcards — hidden immediately so users see the rendered site.
	//   The bundle's own JS would normally remove this, but hiding it via CSS
	//   ensures it never flashes even if JS is momentarily slow.
	$inject .= '
<style>
#__bundler_loading{display:none!important;}
#__bundler_thumbnail{display:none!important;}
html,body{background:#05080F!important;overflow-x:hidden!important;}
*{-webkit-tap-highlight-color:transparent;box-sizing:border-box;}
input,button,select,textarea{font-size:16px!important;}
@supports not (backdrop-filter:blur(1px)){
  [style*="backdrop-filter"]{background:rgba(12,20,38,.96)!important;}
}
</style>';

	$html = preg_replace( '#</head>#i', $inject . '</head>', $html, 1 );

	// ── Placement cycling + floatcard data injection ───────────────────────────
	// ET_PLACEMENTS comes from the PHP array at the top of this file.
	// To add a new "recently placed" scenario, add a row to $et_placements above.
	$placements_json = wp_json_encode( $et_placements );

	$cycling_script = '
<script>
(function(){
  /* ── Data from PHP — edit $et_placements in front-page.php to change these ── */
  var ET_PLACEMENTS=' . $placements_json . ';

  /* Plain text labels for the badge text node */
  var labels=ET_PLACEMENTS.map(function(p){return p.role+"·"+p.weeks+" weeks";});
  var idx=0,cycleStarted=false,mobileFixed=false;

  /* ── Find the placement badge text node ── */
  function findPlacementEl(){
    var el=document.getElementById("et-placed-role");
    if(el&&el.children.length===0) return el;
    var all=document.querySelectorAll("span,div,p");
    for(var i=0;i<all.length;i++){
      var t=all[i].textContent.trim();
      if(all[i].children.length===0&&t.indexOf("weeks")>-1&&
         (t.indexOf("Scientist")>-1||t.indexOf("Developer")>-1||
          t.indexOf("Engineer")>-1||t.indexOf("Analyst")>-1)){
        return all[i];
      }
    }
    return null;
  }

  /* ── Cycle the placement text in the badge ── */
  function startCycling(el){
    if(cycleStarted)return;cycleStarted=true;
    setInterval(function(){
      el.style.transition="opacity 0.4s";el.style.opacity="0";
      setTimeout(function(){
        idx=(idx+1)%labels.length;
        el.textContent=labels[idx];
        el.style.opacity="1";
      },420);
    },3200);
  }

  /* ── Update the secondary floatcard (data-depth="2.4") with a different entry ── */
  function updateFloatCards(){
    var cards=document.querySelectorAll(".et-floatcard[data-depth]");
    cards.forEach(function(card){
      var depth=card.getAttribute("data-depth");
      if(depth!=="2.4") return;
      var p=ET_PLACEMENTS[1]||ET_PLACEMENTS[0]; /* show 2nd placement in secondary card */
      var nodes=card.querySelectorAll("span,div,p");
      for(var i=0;i<nodes.length;i++){
        var t=nodes[i].textContent.trim();
        if(nodes[i].children.length===0&&t.indexOf("weeks")>-1){
          nodes[i].textContent=p.role+" · "+p.weeks+" weeks";
          break;
        }
        if(nodes[i].children.length===0&&(t.indexOf("Placed at")>-1||t.indexOf("placed at")>-1)){
          nodes[i].textContent="Placed at "+p.company;
          break;
        }
      }
    });
  }

  /* ── Mobile layout fixes applied via JS (reliable across DC-generated markup) ── */
  function applyMobileFix(){
    if(mobileFixed||window.innerWidth>900)return;mobileFixed=true;
    var divs=document.querySelectorAll("div");
    for(var i=0;i<divs.length;i++){
      var s=divs[i].style;
      if(!s)continue;
      var gc=s.gridTemplateColumns||"";
      if(gc.indexOf("1fr 1fr")>-1||gc.indexOf("1fr 2fr")>-1||gc.indexOf("2fr 1fr")>-1||
         gc.indexOf("1fr 3fr")>-1||gc.indexOf("3fr 1fr")>-1){
        s.gridTemplateColumns="1fr";s.gap="32px";
      }
      if(gc.indexOf("repeat(3")>-1){s.gridTemplateColumns="1fr";}
      if(gc.indexOf("repeat(5")>-1){s.gridTemplateColumns="repeat(2,1fr)";}
      if(gc.indexOf("repeat(4")>-1){s.gridTemplateColumns="repeat(2,1fr)";}
      var w=s.width||"";
      if(w&&parseInt(w)>window.innerWidth&&w.indexOf("%")===-1&&w.indexOf("vw")===-1){
        s.width="100%";s.maxWidth="100vw";
      }
      var pos=s.position||"";
      var right=s.right||"";
      if(pos==="absolute"&&right&&parseInt(right)<0){s.right="0";}
    }
    document.body.style.overflowX="hidden";
    document.documentElement.style.overflowX="hidden";
  }

  /* ── Poll until DC renders (checks every 400 ms, gives up at 25 s) ── */
  /* Note: #__bundler_thumbnail is hidden via CSS so we do NOT wait for it here. */
  var attempts=0;
  var poll=setInterval(function(){
    attempts++;
    var el=findPlacementEl();
    if(el) startCycling(el);
    applyMobileFix();
    if(attempts===2) updateFloatCards(); /* update secondary card after bundle renders */
    if(cycleStarted&&mobileFixed||attempts>62){clearInterval(poll);}
  },400);
})();
</script>';

	$html = str_replace( '</body>', $cycling_script . '</body>', $html );

	// Gzip the output so the 2.39 MB bundle transfers as ~500 KB.
	if ( function_exists( 'ob_gzhandler' ) && ! ini_get( 'zlib.output_compression' ) ) {
		ob_start( 'ob_gzhandler' );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		ob_end_flush();
	} else {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	exit;
}

// Fallback when the static file is missing.
get_header();
?>
<main id="main" class="site-main" style="display:flex;align-items:center;justify-content:center;min-height:calc(100vh - 80px);">
	<div style="text-align:center;padding:2rem;">
		<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80" style="margin:0 auto 1.5rem;display:block;">
			<rect width="80" height="80" rx="16" fill="#0C1426"/>
			<circle cx="40" cy="40" r="28" fill="none" stroke="#22D3EE" stroke-opacity="0.4" stroke-width="1.5"/>
			<text x="40" y="51" font-family="sans-serif" font-size="26" font-weight="700" fill="#22D3EE" text-anchor="middle">et</text>
		</svg>
		<h1 style="font-size:1.75rem;color:#22D3EE;margin-bottom:0.75rem;">Enterns Tech</h1>
		<p style="color:#6B7280;">The site bundle is missing. Please re-upload <code>static/index.html</code> to the theme folder.</p>
	</div>
</main>
<?php
get_footer();
