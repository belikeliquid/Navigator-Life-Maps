<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Branded emails: the "shared a map with you" note and the invitation to join.
 * One layout built from tables with inline styles, which is what Gmail and Outlook render
 * reliably. Every message also carries a plain-text version.
 */
class NMM_Mail {

    const LOGO    = '/wp-content/uploads/navigator-logo-v3-horizontal-white-1024.png';
    const NAVY    = '#011C2F';
    const NAVY2   = '#0A2D47';
    const TEAL    = '#0BBFBF';
    const SAND    = '#F4F1EA';
    const TAN     = '#E7E4DB';
    const GOLD    = '#C9A45C';
    const FONT    = "'Fira Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif";
    const ABOUT   = 'Navigator Life Maps is where vision meets action. Your ideas find structure and your goals find ground: a mind map for what matters, a Kanban board for what comes next, and a timeline and roadmap for when.';
    const MOTTO   = 'In the ocean of life, you are the navigator.';

    // $m: preheader, heading, paragraphs[] (plain text), map_title, button, url, note, reason
    public static function html( $m ) {
        $e    = function ( $s ) { return esc_html( $s ); };
        $f    = self::FONT;
        $home = home_url( '/' );
        $paras = '';
        foreach ( $m['paragraphs'] as $p ) {
            $paras .= '<p style="margin:0 0 16px;font-family:' . $f . ';font-size:16px;line-height:1.6;color:#3d4a56">' . $e( $p ) . '</p>';
        }
        $card = empty( $m['map_title'] ) ? '' :
            '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:4px 0 22px"><tr>' .
            '<td style="background:' . self::SAND . ';border-left:4px solid ' . self::TEAL . ';border-radius:8px;padding:14px 18px">' .
            '<div style="font-family:' . $f . ';font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#8a8478">Life Map</div>' .
            '<div style="font-family:' . $f . ';font-size:19px;font-weight:600;line-height:1.35;color:' . self::NAVY . ';margin-top:4px">' . $e( $m['map_title'] ) . '</div>' .
            '</td></tr></table>';
        $button =
            '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:6px 0 22px"><tr>' .
            '<td style="border-radius:999px;background:' . self::TEAL . '">' .
            '<a href="' . esc_url( $m['url'] ) . '" style="display:inline-block;padding:14px 28px;font-family:' . $f . ';font-size:16px;font-weight:600;color:' . self::NAVY . ';text-decoration:none;border-radius:999px">' . $e( $m['button'] ) . ' &rarr;</a>' .
            '</td></tr></table>';
        $note = empty( $m['note'] ) ? '' : '<p style="margin:0 0 6px;font-family:' . $f . ';font-size:13.5px;line-height:1.55;color:#6b7280">' . $e( $m['note'] ) . '</p>';

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e( $m['heading'] ) . '</title></head>' .
            '<body style="margin:0;padding:0;background:' . self::TAN . '">' .
            '<div style="display:none;max-height:0;overflow:hidden;opacity:0">' . $e( $m['preheader'] ) . '</div>' .
            '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . self::TAN . '"><tr><td align="center" style="padding:28px 12px">' .
            '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;background:#ffffff;border-radius:16px;overflow:hidden">' .
              // Header: logo on navy
              '<tr><td style="background:' . self::NAVY . ';padding:26px 32px" align="left">' .
                '<a href="' . esc_url( $home ) . '"><img src="' . esc_url( home_url( self::LOGO ) ) . '" width="210" alt="NAVIGATOR" style="display:block;width:210px;max-width:70%;height:auto;border:0;font-family:' . $f . ';font-size:22px;letter-spacing:3px;color:#ffffff"></a>' .
                '<div style="font-family:' . $f . ';font-size:12px;letter-spacing:2px;text-transform:uppercase;color:' . self::GOLD . ';margin-top:12px">Navigating the sea of life</div>' .
              '</td></tr>' .
              // Body
              '<tr><td style="padding:32px 32px 12px">' .
                '<h1 style="margin:0 0 18px;font-family:' . $f . ';font-size:23px;font-weight:600;line-height:1.3;color:' . self::NAVY . '">' . $e( $m['heading'] ) . '</h1>' .
                $paras . $card . $button . $note .
              '</td></tr>' .
              // About + motto
              '<tr><td style="padding:8px 32px 28px">' .
                '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="border-top:1px solid ' . self::TAN . ';padding-top:20px">' .
                  '<p style="margin:0 0 10px;font-family:' . $f . ';font-size:14px;line-height:1.6;color:#4b5563">' . $e( self::ABOUT ) . '</p>' .
                  '<p style="margin:0;font-family:Georgia,\'Times New Roman\',serif;font-style:italic;font-size:15px;color:' . self::NAVY2 . '">' . $e( self::MOTTO ) . '</p>' .
                '</td></tr></table>' .
              '</td></tr>' .
              // Footer
              '<tr><td style="background:' . self::NAVY . ';padding:18px 32px" align="left">' .
                '<a href="' . esc_url( $home ) . '" style="font-family:' . $f . ';font-size:13px;color:' . self::TEAL . ';text-decoration:none">navigatorproject.org</a>' .
                '<span style="color:#4b6070;padding:0 8px">|</span>' .
                '<a href="' . esc_url( home_url( '/maps/' ) ) . '" style="font-family:' . $f . ';font-size:13px;color:' . self::TEAL . ';text-decoration:none">Your Life Maps</a>' .
                '<div style="font-family:' . $f . ';font-size:12px;line-height:1.5;color:#8fa1b0;margin-top:8px">' . $e( $m['reason'] ) . '</div>' .
              '</td></tr>' .
            '</table>' .
            '</td></tr></table></body></html>';
    }

    public static function text( $m ) {
        return $m['heading'] . "\n\n" . implode( "\n\n", $m['paragraphs'] ) . "\n\n" .
            ( empty( $m['map_title'] ) ? '' : 'Life Map: ' . $m['map_title'] . "\n\n" ) .
            $m['button'] . ': ' . $m['url'] . "\n\n" .
            ( empty( $m['note'] ) ? '' : $m['note'] . "\n\n" ) .
            self::ABOUT . "\n" . self::MOTTO . "\n\n" .
            home_url( '/' ) . "\n" . $m['reason'] . "\n";
    }

    // Sends the HTML with its plain-text version alongside
    public static function send( $to, $subject, $m ) {
        $text = self::text( $m );
        $alt  = function ( $phpmailer ) use ( $text ) { $phpmailer->AltBody = $text; };
        add_action( 'phpmailer_init', $alt );
        $sent = wp_mail( $to, $subject, self::html( $m ), [ 'Content-Type: text/html; charset=UTF-8' ] );
        remove_action( 'phpmailer_init', $alt );
        return $sent;
    }
}
