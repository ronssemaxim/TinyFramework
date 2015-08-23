<?php
require_once 'PHPMailer/PHPMailerAutoload.php';

class Mail {
	public static function Send($from, $to, $subject, $content) {
		$mail = new PHPMailer;
		$mail->isSMTP();
		$mail->Host = 'relay.proximus.be';
		$mail->From = $from;
		$mail->FromName = $from;
		foreach ($to as $key => $value) {
			$mail->addAddress($value, $key);     // Add a recipient
		}
		$mail->addReplyTo($from, $from);
		$mail->isHTML(false);

		$mail->Subject = $subject;
		$mail->Body    = $content;
		return $mail->send();
	}
}