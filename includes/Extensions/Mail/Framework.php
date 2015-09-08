<?php
require_once dirname(__FILE__) . '/PHPMailer/PHPMailerAutoload.php';

class Mail {
	public static function SendMail($from, $to, $subject, $content, $isHTML = false) {
		$mail = new PHPMailer;
		$mail->isSMTP();
		$mail->Host = 'relay.proximus.be';

		if(is_array($from)) {
			$mail->From = array_values(array_shift(array_values($from)));
			$mail->FromName = array_values(array_shift(array_keys($from)));
		}
		else {
			$mail->From = $from;
			$mail->FromName = $from;
		}

		foreach ($to as $key => $value) {
			$mail->addAddress($value, $key);     // Add a recipient
		}
		if(is_array($from))
			$mail->addReplyTo(array_values(array_shift(array_keys($from))), array_values(array_shift(array_values($from))));
		else
			$mail->addReplyTo($from, $from);
		$mail->isHTML($isHTML);

		$mail->Subject = $subject;
		$mail->Body    = $content;
		return $mail->send();
	}
}