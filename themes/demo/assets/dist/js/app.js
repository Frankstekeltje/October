/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!**************************************!*\
  !*** ./themes/demo/assets/js/app.js ***!
  \**************************************/
function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } }

function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); Object.defineProperty(Constructor, "prototype", { writable: false }); return Constructor; }

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

/*
 * Application
 */
$(function () {
  /*
   * Auto hide navbar
   */
  var $header = $('.navbar-autohide'),
      scrolling = false,
      previousTop = 0,
      scrollDelta = 10,
      scrollOffset = 150;
  $(window).on('scroll', function () {
    if (!scrolling) {
      scrolling = true;

      if (!window.requestAnimationFrame) {
        setTimeout(autoHideHeader, 250);
      } else {
        requestAnimationFrame(autoHideHeader);
      }
    }
  });

  function autoHideHeader() {
    var currentTop = $(window).scrollTop(); // Scrolling up

    if (previousTop - currentTop > scrollDelta) {
      $header.removeClass('is-hidden');
    } else if (currentTop - previousTop > scrollDelta && currentTop > scrollOffset) {
      // Scrolling down
      $header.addClass('is-hidden');
    }

    previousTop = currentTop;
    scrolling = false;
  }
}); // import {sayHi, sayBye} from "./sayhi";
//
// sayHi('john');
// sayBye('jeko');

// var helloWorld = /*#__PURE__*/_createClass(function helloWorld() {
//   _classCallCheck(this, helloWorld);
//
//   alert('hello world');
// });

new helloWorld();
/******/ })()
;