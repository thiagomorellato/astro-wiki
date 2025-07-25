<?php

/**
 * QuestyCaptcha class
 *
 * @file
 * @author Benjamin Lees <emufarmers@gmail.com>
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\ConfirmEdit\QuestyCaptcha;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\ConfirmEdit\Auth\CaptchaAuthenticationRequest;
use MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha;
use MediaWiki\Extension\ConfirmEdit\Store\CaptchaStore;
use MediaWiki\Html\Html;
use MediaWiki\Xml\Xml;

class QuestyCaptcha extends SimpleCaptcha {
	/**
	 * @var string used for questycaptcha-edit, questycaptcha-addurl, questycaptcha-badlogin,
	 * questycaptcha-createaccount, questycaptcha-create, questycaptcha-sendemail via getMessage()
	 */
	protected static $messagePrefix = 'questycaptcha-';

	/**
	 * Validate a CAPTCHA response
	 *
	 * @note Trimming done as per T368112
	 *
	 * @param string $answer
	 * @param array $info
	 * @return bool
	 */
	protected function keyMatch( $answer, $info ) {
		if ( is_array( $info['answer'] ) ) {
			return in_array( strtolower( trim( $answer ) ), array_map( 'strtolower', $info['answer'] ) );
		} else {
			return strtolower( trim( $answer ) ) == strtolower( $info['answer'] );
		}
	}

	/** @inheritDoc */
	public function describeCaptchaType() {
		return [
			'type' => 'question',
			'mime' => 'text/html',
		];
	}

	/** @inheritDoc */
	public function getCaptcha() {
		global $wgCaptchaQuestions;

		// Backwards compatibility
		if ( $wgCaptchaQuestions === array_values( $wgCaptchaQuestions ) ) {
			return $wgCaptchaQuestions[ random_int( 0, count( $wgCaptchaQuestions ) - 1 ) ];
		}

		$question = array_rand( $wgCaptchaQuestions, 1 );
		$answer = $wgCaptchaQuestions[ $question ];
		return [ 'question' => $question, 'answer' => $answer ];
	}

	/**
	 * @param int $tabIndex
	 * @return array
	 */
	public function getFormInformation( $tabIndex = 1 ) {
		$captcha = $this->getCaptcha();
		if ( !$captcha ) {
			die(
				"No questions found; set some in LocalSettings.php using the format from QuestyCaptcha.php."
			);
		}
		$index = $this->storeCaptcha( $captcha );
		return [
			'html' => "<p><label for=\"wpCaptchaWord\">{$captcha['question']}</label> " .
				Html::element( 'input', [
					'name' => 'wpCaptchaWord',
					'id'   => 'wpCaptchaWord',
					'required',
					'autocomplete' => 'off',
					// tab in before the edit textarea
					'tabindex' => $tabIndex ]
				) . "</p>\n" .
				Xml::element( 'input', [
					'type'  => 'hidden',
					'name'  => 'wpCaptchaId',
					'id'    => 'wpCaptchaId',
					'value' => $index ]
				)
		];
	}

	public function showHelp() {
		$context = RequestContext::getMain();
		$out = $context->getOutput();
		$out->setPageTitleMsg( $context->msg( 'captchahelp-title' ) );
		$out->addWikiMsg( 'questycaptchahelp-text' );
		if ( CaptchaStore::get()->cookiesNeeded() ) {
			$out->addWikiMsg( 'captchahelp-cookies-needed' );
		}
	}

	/**
	 * @param array $captchaData
	 * @param string $id
	 * @return mixed
	 */
	public function getCaptchaInfo( $captchaData, $id ) {
		return $captchaData['question'];
	}

	/**
	 * @param array $requests
	 * @param array $fieldInfo
	 * @param array &$formDescriptor
	 * @param string $action
	 */
	public function onAuthChangeFormFields( array $requests, array $fieldInfo,
		array &$formDescriptor, $action ) {
		/** @var CaptchaAuthenticationRequest $req */
		$req =
			AuthenticationRequest::getRequestByClass( $requests,
				CaptchaAuthenticationRequest::class, true );
		if ( !$req ) {
			return;
		}

		// declare RAW HTML output.
		$formDescriptor['captchaInfo']['raw'] = true;
		$formDescriptor['captchaWord']['label-message'] = null;
	}
}

class_alias( QuestyCaptcha::class, 'QuestyCaptcha' );
