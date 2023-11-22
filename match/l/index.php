<?php

/*
Copyright 2023 Bård Bjerke Johannessen <bbj@bbj.io>
   
This file is part of the LB5JJ tools website.

The LB5JJ tools website is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by the Free
Software Foundation, either version 3 of the License, or (at your option) any
later version.

The LB5JJ tools website is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
details.

You should have received a copy of the GNU General Public License along with
the LB5JJ tools website. If not, see <https://www.gnu.org/licenses/>. 
*/

function safe($key) {
	if(isset($_POST[$key]))
		return htmlspecialchars($_POST[$key]);
}

function nice($value, $unit = '') {
	if ($value == 0) return '0.00' . $unit;

	$sign = ($value < 0) ? '-' : '';

	$steps = array(
		array('limit' => 1000000000000,  'power' => '¹²',  'unit' => 'T'),
		array('limit' => 1000000000,     'power' => '⁹',   'unit' => 'G'),
		array('limit' => 1000000,        'power' => '⁶',   'unit' => 'M'),
		array('limit' => 1000,           'power' => '³',   'unit' => 'k'),
		array('limit' => 1,              'power' => false, 'unit' => ''),
		array('limit' => 0.001,          'power' => '⁻³',  'unit' => 'm'),
		array('limit' => 0.000001,       'power' => '⁻⁶',  'unit' => 'µ'),
		array('limit' => 0.000000001,    'power' => '⁻⁹',  'unit' => 'n'),
		array('limit' => 0.000000000001, 'power' => '⁻¹²', 'unit' => 'p')
	);

	foreach ($steps as $step) {
		if (abs($value) >= $step['limit']) {
			$postfix = $unit ? $step['unit'] . $unit: ($step['power'] ? '*10' : '') . $step['power'];
			return $sign . number_format(abs($value) / $step['limit'], 2) . $postfix;
		}
	}

	return $sign . number_format(abs($value) / 0.000000000000001, 2) . '*10⁻¹⁵';
}

function int($key, $min = null, $max = null) {
	if (!isset($_POST[$key])) return null;

	$opts = array('flags' => FILTER_NULL_ON_FAILURE);

	if (!is_null($min)) $array['min_range'] = $min;
	if (!is_null($max)) $array['max_range'] = $max;

	return filter_var($_POST[$key], FILTER_VALIDATE_INT, $opts);
}

function float($key, $min = null, $max = null) {
	if (!isset($_POST[$key])) return null;

	$opts = array('flags' => FILTER_FLAG_ALLOW_FRACTION | FILTER_NULL_ON_FAILURE);

	if (!is_null($min)) $array['min_range'] = $min;
	if (!is_null($max)) $array['max_range'] = $max;

	return filter_var($_POST[$key], FILTER_VALIDATE_FLOAT, $opts);
}

class impedance {
	public float $r;
	public float $x;
	public float $f;

	public function __construct(float $r, float $x, float $f) {
		$this->r = $r;
		$this->x = $x;
		$this->f = $f;
	}

	public function omega() {
		return 2 * pi() * $this->f;
	}

	public function q() {
		return $this->x / $this->r;
	}

	public function rp() {
		return $this->r * (1 + $this->q() ** 2);
	}

	public function lowpass($load, $shunt = 'source') {
		if ($this->f != $load->f)
			throw new Exception('Source and load impedance at different frequencies');

		if ($load->r > $this->rp())
			return $shunt == 'load' ? false : $load->lowpass($this, 'load');

		$q = sqrt(($this->rp() / $load->r) - 1);
			
		$c_ = -$this->q() / $this->rp() / $this->omega();
		$cp = $q / $this->rp() / $this->omega();
		$l_ = $load->x / $this->omega();
		$ls = $q * $load->r / $this->omega();
			
		$c = $cp - $c_;
		$l = $ls - $l_;

		if (!($c > 0 && $l > 0))
			return $shunt == 'load' ? false : $load->lowpass($this, 'load');

		return array(
			'type'   => 'lowpass',
			'shunt'  => $shunt,
			'q'      => $q,
			'c'      => $c,
			'l'      => $l
		);
	}

	public function highpass($load, $shunt = 'source') {
		if ($this->f != $load->f)
			throw new Exception('Source and load impedance at different frequencies');

		if ($load->r > $this->rp())
			return $shunt == 'load' ? false : $load->highpass($this, 'load');

		$q = sqrt(($this->rp() / $load->r) - 1);

		$cs = 1 / $q / $this->omega() / $load->r;
		$lp = $this->rp() / $this->omega() / $q;

		if ($load->x == 0) {
			$c = $cs;
		} else {
			$c_ = -1 / $this->omega() / $load->x;
			$c = ($c_ * $cs) / ($c_ - $cs);
		}

		if ($this->x == 0) {
			$l = $lp;
		} else {
			$l_ = (1 + ($this->q() ** 2)) * $this->x / $this->omega() / $this->q() / $this->q();
			$l = ($l_ * $lp) / ($l_ - $lp);
		}

		if (is_nan($c) || $c <= 0 || is_nan($l) || $l <= 0)
			return $shunt == 'load' ? false : $load->highpass($this, 'load');
	
		return array(
			'type'   => 'highpass',
			'shunt'  => $shunt,
			'q'      => $q,
			'c'      => $c,
			'l'      => $l
		);
	}
}

$error = array();
$solutions = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (null === (($rs  = int('rs', 1))))              $error['rs'] = true;
	if (null === (($xs  = int('xs'))))                 $error['xs'] = true;
	if (null === (($rl  = int('rl', 1))))              $error['rl'] = true;
	if (null === (($xl  = int('xl'))))                 $error['xl'] = true;
	if (null === (($mul = int('mul', 0, 1000000000)))) $error['mul'] = true;
	if (null === (($f0  = float('f0', 1))))            $error['f0'] = true;

	if (!sizeof($error)) {
		$source = new impedance($rs, $xs, $f0 * $mul);
		$load   = new impedance($rl, $xl, $f0 * $mul);

		$lowpass  = $source->lowpass($load);		
		$highpass = $source->highpass($load);
	}
}

include "template.html";

?>
