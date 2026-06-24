/* Enterns Portal — portal.js */
(function ($) {
  'use strict';

  var ENP = window.ENP || {};
  if (!ENP.ajaxUrl) return;

  var portalNonce = ENP.nonce || '';

  function showMsg($el, msg, ok) {
    $el.removeClass('is-ok is-err')
       .addClass(ok ? 'is-ok' : 'is-err')
       .text(msg);
  }
  function clearMsg($el) { $el.removeClass('is-ok is-err').text(''); }

  // ── Skills save ─────────────────────────────────────────────────────────────
  var $skillsForm = $('#enp-skills-form');
  if ($skillsForm.length) {
    $skillsForm.on('submit', function (e) {
      e.preventDefault();
      var $btn = $skillsForm.find('button[type=submit]');
      var $msg = $('#enp-skills-msg');
      clearMsg($msg);
      $btn.prop('disabled', true).text('Saving…');

      $.post(ENP.ajaxUrl, {
        action:     'enp_update_skills',
        nonce:      portalNonce,
        tech_stack: $('#enp-skills-input').val(),
      }).done(function (res) {
        if (res.success) {
          showMsg($msg, 'Skills saved! Refreshing…', true);
          setTimeout(function () { window.location.reload(); }, 700);
        } else {
          showMsg($msg, res.data || 'Error saving skills.', false);
          $btn.prop('disabled', false).text('Save Skills');
        }
      }).fail(function () {
        showMsg($msg, 'Network error. Please try again.', false);
        $btn.prop('disabled', false).text('Save Skills');
      });
    });
  }

  // ── Pick mentor ─────────────────────────────────────────────────────────────
  $(document).on('click', '.enp-pick-mentor-btn', function () {
    var $btn   = $(this);
    var mName  = $btn.data('mentor-name') || 'this mentor';
    var $msg   = $('#enp-pick-msg');
    if (!confirm('Pick ' + mName + ' as your mentor?')) return;
    $btn.prop('disabled', true).text('Assigning…');
    clearMsg($msg);

    $.post(ENP.ajaxUrl, {
      action:    'enp_pick_mentor',
      nonce:     portalNonce,
      mentor_id: $btn.data('mentor-id'),
    }).done(function (res) {
      if (res.success) {
        showMsg($msg, 'Mentor assigned! Refreshing…', true);
        setTimeout(function () { window.location.reload(); }, 900);
      } else {
        showMsg($msg, res.data || 'Error assigning mentor.', false);
        $btn.prop('disabled', false).text('Pick This Mentor');
      }
    }).fail(function () {
      showMsg($msg, 'Network error. Please try again.', false);
      $btn.prop('disabled', false).text('Pick This Mentor');
    });
  });

  // ── Request mentor change ────────────────────────────────────────────────────
  var $reqBtn     = $('#enp-request-change-btn');
  var $changeForm = $('#enp-change-form');
  if ($reqBtn.length) {
    $reqBtn.on('click', function () { $changeForm.toggle(); });

    $('#enp-change-cancel').on('click', function () { $changeForm.hide(); });

    $('#enp-change-submit').on('click', function () {
      var reason    = $('#enp-change-reason').val().trim();
      var newMentor = parseInt($('#enp-change-mentor').val(), 10) || 0;
      var $btn      = $(this);
      var $msg      = $('#enp-change-msg');
      clearMsg($msg);

      if (!reason) { showMsg($msg, 'Please enter a reason.', false); return; }

      $btn.prop('disabled', true).text('Submitting…');

      $.post(ENP.ajaxUrl, {
        action:        'enp_request_mentor_change',
        nonce:         portalNonce,
        reason:        reason,
        new_mentor_id: newMentor,
      }).done(function (res) {
        if (res.success) {
          showMsg($msg, res.data || 'Request submitted!', true);
          setTimeout(function () { window.location.reload(); }, 1100);
        } else {
          showMsg($msg, res.data || 'Error submitting request.', false);
          $btn.prop('disabled', false).text('Submit Request');
        }
      }).fail(function () {
        showMsg($msg, 'Network error. Please try again.', false);
        $btn.prop('disabled', false).text('Submit Request');
      });
    });
  }

  // ── Plan upgrade (Razorpay) ──────────────────────────────────────────────────
  $(document).on('click', '.enp-upgrade-btn', function () {
    if (!ENP.rzpConfigured) return;

    var $btn      = $(this);
    var planId    = $btn.data('plan-id');
    var planName  = $btn.data('plan-name');
    var planPrice = $btn.data('plan-price');
    var email     = ENP.currentEmail || '';
    var $msg      = $('#enp-upgrade-msg');
    clearMsg($msg);

    if (!email) {
      showMsg($msg, 'Cannot determine your email. Please contact support.', false);
      return;
    }
    if (!confirm('Upgrade to ' + planName + ' (' + planPrice + ')?\n\nYou will be charged the full plan price.')) return;

    $btn.prop('disabled', true).text('Creating order…');

    $.post(ENP.ajaxUrl, {
      action:  'enp_create_razorpay_order',
      nonce:   ENP.rzpNonce,
      plan_id: planId,
      email:   email,
    }).done(function (res) {
      if (!res.success) {
        showMsg($msg, res.data || 'Could not create payment order.', false);
        $btn.prop('disabled', false).text('Upgrade');
        return;
      }
      var d = res.data;
      var opts = {
        key:        d.key_id,
        amount:     d.amount,
        currency:   d.currency,
        order_id:   d.order_id,
        name:       'Enterns Tech',
        description: planName,
        prefill:    { email: email },
        theme:      { color: '#22D3EE' },
        handler: function (rzpRes) {
          $btn.text('Verifying…');
          $.post(ENP.ajaxUrl, {
            action:               'enp_verify_razorpay_payment',
            nonce:                ENP.rzpNonce,
            razorpay_order_id:   rzpRes.razorpay_order_id,
            razorpay_payment_id: rzpRes.razorpay_payment_id,
            razorpay_signature:  rzpRes.razorpay_signature,
            payment_id:          d.payment_id,
            email:               email,
          }).done(function (vRes) {
            if (vRes.success) {
              showMsg($msg, 'Plan upgraded! Refreshing…', true);
              setTimeout(function () { window.location.reload(); }, 1200);
            } else {
              showMsg($msg, vRes.data || 'Verification failed. Contact support.', false);
              $btn.prop('disabled', false).text('Upgrade');
            }
          }).fail(function () {
            showMsg($msg,
              'Network error verifying payment. Contact support with payment ID: ' +
              rzpRes.razorpay_payment_id, false);
            $btn.prop('disabled', false).text('Upgrade');
          });
        },
        modal: {
          ondismiss: function () {
            showMsg($msg, 'Payment cancelled.', false);
            $btn.prop('disabled', false).text('Upgrade');
          },
        },
      };
      var rzp = new Razorpay(opts);
      rzp.open();
    }).fail(function () {
      showMsg($msg, 'Network error. Please try again.', false);
      $btn.prop('disabled', false).text('Upgrade');
    });
  });

})(jQuery);
