<?php

// this is a demo of colored functions
// how to test:
// execute in console (in a current folder)
// > kphp2cpp index.php -M cli
// compilation will fail with the message "Potential performance leak"
//
// if you
// 1) either remove all "@kphp-color" from a demo
// 2) or add "@kphp-color slow-ignore" over Logger::debug()
// then compilation will succeed
//
// read the docs for the concept:
// https://github.com/VKCOM/nocolor/blob/master/docs/introducing_colors.md

// to make KPHP see this class, it must be used somehow
if (0) {
  $_ = KphpConfiguration::class;
}

require_once 'colored-demo.php';
coloredDemoThatDoesNotCompile();
