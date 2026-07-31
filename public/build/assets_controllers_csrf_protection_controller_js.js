"use strict";
(self["webpackChunk"] = self["webpackChunk"] || []).push([["assets_controllers_csrf_protection_controller_js"],{

/***/ "./assets/controllers/csrf_protection_controller.js"
/*!**********************************************************!*\
  !*** ./assets/controllers/csrf_protection_controller.js ***!
  \**********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__),
/* harmony export */   generateCsrfHeaders: () => (/* binding */ generateCsrfHeaders),
/* harmony export */   generateCsrfToken: () => (/* binding */ generateCsrfToken),
/* harmony export */   removeCsrfToken: () => (/* binding */ removeCsrfToken)
/* harmony export */ });
/* harmony import */ var core_js_modules_es_array_buffer_detached_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! core-js/modules/es.array-buffer.detached.js */ "./node_modules/core-js/modules/es.array-buffer.detached.js");
/* harmony import */ var core_js_modules_es_array_buffer_detached_js__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_array_buffer_detached_js__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var core_js_modules_es_array_buffer_transfer_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! core-js/modules/es.array-buffer.transfer.js */ "./node_modules/core-js/modules/es.array-buffer.transfer.js");
/* harmony import */ var core_js_modules_es_array_buffer_transfer_js__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_array_buffer_transfer_js__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var core_js_modules_es_array_buffer_transfer_to_fixed_length_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! core-js/modules/es.array-buffer.transfer-to-fixed-length.js */ "./node_modules/core-js/modules/es.array-buffer.transfer-to-fixed-length.js");
/* harmony import */ var core_js_modules_es_array_buffer_transfer_to_fixed_length_js__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_array_buffer_transfer_to_fixed_length_js__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var core_js_modules_es_iterator_constructor_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! core-js/modules/es.iterator.constructor.js */ "./node_modules/core-js/modules/es.iterator.constructor.js");
/* harmony import */ var core_js_modules_es_iterator_constructor_js__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_iterator_constructor_js__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var core_js_modules_es_iterator_map_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! core-js/modules/es.iterator.map.js */ "./node_modules/core-js/modules/es.iterator.map.js");
/* harmony import */ var core_js_modules_es_iterator_map_js__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_iterator_map_js__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var core_js_modules_es_typed_array_to_reversed_js__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! core-js/modules/es.typed-array.to-reversed.js */ "./node_modules/core-js/modules/es.typed-array.to-reversed.js");
/* harmony import */ var core_js_modules_es_typed_array_to_reversed_js__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_typed_array_to_reversed_js__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var core_js_modules_es_typed_array_to_sorted_js__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! core-js/modules/es.typed-array.to-sorted.js */ "./node_modules/core-js/modules/es.typed-array.to-sorted.js");
/* harmony import */ var core_js_modules_es_typed_array_to_sorted_js__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_typed_array_to_sorted_js__WEBPACK_IMPORTED_MODULE_6__);
/* harmony import */ var core_js_modules_es_typed_array_with_js__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! core-js/modules/es.typed-array.with.js */ "./node_modules/core-js/modules/es.typed-array.with.js");
/* harmony import */ var core_js_modules_es_typed_array_with_js__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_typed_array_with_js__WEBPACK_IMPORTED_MODULE_7__);
/* harmony import */ var core_js_modules_es_uint8_array_set_from_base64_js__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! core-js/modules/es.uint8-array.set-from-base64.js */ "./node_modules/core-js/modules/es.uint8-array.set-from-base64.js");
/* harmony import */ var core_js_modules_es_uint8_array_set_from_base64_js__WEBPACK_IMPORTED_MODULE_8___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_uint8_array_set_from_base64_js__WEBPACK_IMPORTED_MODULE_8__);
/* harmony import */ var core_js_modules_es_uint8_array_set_from_hex_js__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! core-js/modules/es.uint8-array.set-from-hex.js */ "./node_modules/core-js/modules/es.uint8-array.set-from-hex.js");
/* harmony import */ var core_js_modules_es_uint8_array_set_from_hex_js__WEBPACK_IMPORTED_MODULE_9___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_uint8_array_set_from_hex_js__WEBPACK_IMPORTED_MODULE_9__);
/* harmony import */ var core_js_modules_es_uint8_array_to_base64_js__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! core-js/modules/es.uint8-array.to-base64.js */ "./node_modules/core-js/modules/es.uint8-array.to-base64.js");
/* harmony import */ var core_js_modules_es_uint8_array_to_base64_js__WEBPACK_IMPORTED_MODULE_10___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_uint8_array_to_base64_js__WEBPACK_IMPORTED_MODULE_10__);
/* harmony import */ var core_js_modules_es_uint8_array_to_hex_js__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! core-js/modules/es.uint8-array.to-hex.js */ "./node_modules/core-js/modules/es.uint8-array.to-hex.js");
/* harmony import */ var core_js_modules_es_uint8_array_to_hex_js__WEBPACK_IMPORTED_MODULE_11___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_es_uint8_array_to_hex_js__WEBPACK_IMPORTED_MODULE_11__);
/* harmony import */ var core_js_modules_web_dom_exception_stack_js__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(/*! core-js/modules/web.dom-exception.stack.js */ "./node_modules/core-js/modules/web.dom-exception.stack.js");
/* harmony import */ var core_js_modules_web_dom_exception_stack_js__WEBPACK_IMPORTED_MODULE_12___default = /*#__PURE__*/__webpack_require__.n(core_js_modules_web_dom_exception_stack_js__WEBPACK_IMPORTED_MODULE_12__);













const nameCheck = /^[-_a-zA-Z0-9]{4,22}$/;
const tokenCheck = /^[-_/+a-zA-Z0-9]{24,}$/;

// Generate and double-submit a CSRF token in a form field and a cookie, as defined by Symfony's SameOriginCsrfTokenManager
// Use `form.requestSubmit()` to ensure that the submit event is triggered. Using `form.submit()` will not trigger the event
// and thus this event-listener will not be executed.
document.addEventListener('submit', function (event) {
  generateCsrfToken(event.target);
}, true);

// When @hotwired/turbo handles form submissions, send the CSRF token in a header in addition to a cookie
// The `framework.csrf_protection.check_header` config option needs to be enabled for the header to be checked
document.addEventListener('turbo:submit-start', function (event) {
  const h = generateCsrfHeaders(event.detail.formSubmission.formElement);
  Object.keys(h).map(function (k) {
    event.detail.formSubmission.fetchRequest.headers[k] = h[k];
  });
});

// When @hotwired/turbo handles form submissions, remove the CSRF cookie once a form has been submitted
document.addEventListener('turbo:submit-end', function (event) {
  removeCsrfToken(event.detail.formSubmission.formElement);
});
function generateCsrfToken(formElement) {
  const csrfField = formElement.querySelector('input[data-controller="csrf-protection"], input[name="_csrf_token"]');
  if (!csrfField) {
    return;
  }
  let csrfCookie = csrfField.getAttribute('data-csrf-protection-cookie-value');
  let csrfToken = csrfField.value;
  if (!csrfCookie && nameCheck.test(csrfToken)) {
    csrfField.setAttribute('data-csrf-protection-cookie-value', csrfCookie = csrfToken);
    csrfField.defaultValue = csrfToken = btoa(String.fromCharCode.apply(null, (window.crypto || window.msCrypto).getRandomValues(new Uint8Array(18))));
  }
  csrfField.dispatchEvent(new Event('change', {
    bubbles: true
  }));
  if (csrfCookie && tokenCheck.test(csrfToken)) {
    const cookie = csrfCookie + '_' + csrfToken + '=' + csrfCookie + '; path=/; samesite=strict';
    document.cookie = window.location.protocol === 'https:' ? '__Host-' + cookie + '; secure' : cookie;
  }
}
function generateCsrfHeaders(formElement) {
  const headers = {};
  const csrfField = formElement.querySelector('input[data-controller="csrf-protection"], input[name="_csrf_token"]');
  if (!csrfField) {
    return headers;
  }
  const csrfCookie = csrfField.getAttribute('data-csrf-protection-cookie-value');
  if (tokenCheck.test(csrfField.value) && nameCheck.test(csrfCookie)) {
    headers[csrfCookie] = csrfField.value;
  }
  return headers;
}
function removeCsrfToken(formElement) {
  const csrfField = formElement.querySelector('input[data-controller="csrf-protection"], input[name="_csrf_token"]');
  if (!csrfField) {
    return;
  }
  const csrfCookie = csrfField.getAttribute('data-csrf-protection-cookie-value');
  if (tokenCheck.test(csrfField.value) && nameCheck.test(csrfCookie)) {
    const cookie = csrfCookie + '_' + csrfField.value + '=0; path=/; samesite=strict; max-age=0';
    document.cookie = window.location.protocol === 'https:' ? '__Host-' + cookie + '; secure' : cookie;
  }
}

/* stimulusFetch: 'lazy' */
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ('csrf-protection-controller');

/***/ }

}]);
//# sourceMappingURL=data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJmaWxlIjoiYXNzZXRzX2NvbnRyb2xsZXJzX2NzcmZfcHJvdGVjdGlvbl9jb250cm9sbGVyX2pzLmpzIiwibWFwcGluZ3MiOiI7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7QUFBQSxNQUFNLFNBQVMsR0FBRyx1QkFBdUI7QUFDekMsTUFBTSxVQUFVLEdBQUcsd0JBQXdCOztBQUUzQztBQUNBO0FBQ0E7QUFDQSxRQUFRLENBQUMsZ0JBQWdCLENBQUMsUUFBUSxFQUFFLFVBQVUsS0FBSyxFQUFFO0VBQ2pELGlCQUFpQixDQUFDLEtBQUssQ0FBQyxNQUFNLENBQUM7QUFDbkMsQ0FBQyxFQUFFLElBQUksQ0FBQzs7QUFFUjtBQUNBO0FBQ0EsUUFBUSxDQUFDLGdCQUFnQixDQUFDLG9CQUFvQixFQUFFLFVBQVUsS0FBSyxFQUFFO0VBQzdELE1BQU0sQ0FBQyxHQUFHLG1CQUFtQixDQUFDLEtBQUssQ0FBQyxNQUFNLENBQUMsY0FBYyxDQUFDLFdBQVcsQ0FBQztFQUN0RSxNQUFNLENBQUMsSUFBSSxDQUFDLENBQUMsQ0FBQyxDQUFDLEdBQUcsQ0FBQyxVQUFVLENBQUMsRUFBRTtJQUM1QixLQUFLLENBQUMsTUFBTSxDQUFDLGNBQWMsQ0FBQyxZQUFZLENBQUMsT0FBTyxDQUFDLENBQUMsQ0FBQyxHQUFHLENBQUMsQ0FBQyxDQUFDLENBQUM7RUFDOUQsQ0FBQyxDQUFDO0FBQ04sQ0FBQyxDQUFDOztBQUVGO0FBQ0EsUUFBUSxDQUFDLGdCQUFnQixDQUFDLGtCQUFrQixFQUFFLFVBQVUsS0FBSyxFQUFFO0VBQzNELGVBQWUsQ0FBQyxLQUFLLENBQUMsTUFBTSxDQUFDLGNBQWMsQ0FBQyxXQUFXLENBQUM7QUFDNUQsQ0FBQyxDQUFDO0FBRUssU0FBUyxpQkFBaUIsQ0FBRSxXQUFXLEVBQUU7RUFDNUMsTUFBTSxTQUFTLEdBQUcsV0FBVyxDQUFDLGFBQWEsQ0FBQyxxRUFBcUUsQ0FBQztFQUVsSCxJQUFJLENBQUMsU0FBUyxFQUFFO0lBQ1o7RUFDSjtFQUVBLElBQUksVUFBVSxHQUFHLFNBQVMsQ0FBQyxZQUFZLENBQUMsbUNBQW1DLENBQUM7RUFDNUUsSUFBSSxTQUFTLEdBQUcsU0FBUyxDQUFDLEtBQUs7RUFFL0IsSUFBSSxDQUFDLFVBQVUsSUFBSSxTQUFTLENBQUMsSUFBSSxDQUFDLFNBQVMsQ0FBQyxFQUFFO0lBQzFDLFNBQVMsQ0FBQyxZQUFZLENBQUMsbUNBQW1DLEVBQUUsVUFBVSxHQUFHLFNBQVMsQ0FBQztJQUNuRixTQUFTLENBQUMsWUFBWSxHQUFHLFNBQVMsR0FBRyxJQUFJLENBQUMsTUFBTSxDQUFDLFlBQVksQ0FBQyxLQUFLLENBQUMsSUFBSSxFQUFFLENBQUMsTUFBTSxDQUFDLE1BQU0sSUFBSSxNQUFNLENBQUMsUUFBUSxFQUFFLGVBQWUsQ0FBQyxJQUFJLFVBQVUsQ0FBQyxFQUFFLENBQUMsQ0FBQyxDQUFDLENBQUM7RUFDdEo7RUFDQSxTQUFTLENBQUMsYUFBYSxDQUFDLElBQUksS0FBSyxDQUFDLFFBQVEsRUFBRTtJQUFFLE9BQU8sRUFBRTtFQUFLLENBQUMsQ0FBQyxDQUFDO0VBRS9ELElBQUksVUFBVSxJQUFJLFVBQVUsQ0FBQyxJQUFJLENBQUMsU0FBUyxDQUFDLEVBQUU7SUFDMUMsTUFBTSxNQUFNLEdBQUcsVUFBVSxHQUFHLEdBQUcsR0FBRyxTQUFTLEdBQUcsR0FBRyxHQUFHLFVBQVUsR0FBRywyQkFBMkI7SUFDNUYsUUFBUSxDQUFDLE1BQU0sR0FBRyxNQUFNLENBQUMsUUFBUSxDQUFDLFFBQVEsS0FBSyxRQUFRLEdBQUcsU0FBUyxHQUFHLE1BQU0sR0FBRyxVQUFVLEdBQUcsTUFBTTtFQUN0RztBQUNKO0FBRU8sU0FBUyxtQkFBbUIsQ0FBRSxXQUFXLEVBQUU7RUFDOUMsTUFBTSxPQUFPLEdBQUcsQ0FBQyxDQUFDO0VBQ2xCLE1BQU0sU0FBUyxHQUFHLFdBQVcsQ0FBQyxhQUFhLENBQUMscUVBQXFFLENBQUM7RUFFbEgsSUFBSSxDQUFDLFNBQVMsRUFBRTtJQUNaLE9BQU8sT0FBTztFQUNsQjtFQUVBLE1BQU0sVUFBVSxHQUFHLFNBQVMsQ0FBQyxZQUFZLENBQUMsbUNBQW1DLENBQUM7RUFFOUUsSUFBSSxVQUFVLENBQUMsSUFBSSxDQUFDLFNBQVMsQ0FBQyxLQUFLLENBQUMsSUFBSSxTQUFTLENBQUMsSUFBSSxDQUFDLFVBQVUsQ0FBQyxFQUFFO0lBQ2hFLE9BQU8sQ0FBQyxVQUFVLENBQUMsR0FBRyxTQUFTLENBQUMsS0FBSztFQUN6QztFQUVBLE9BQU8sT0FBTztBQUNsQjtBQUVPLFNBQVMsZUFBZSxDQUFFLFdBQVcsRUFBRTtFQUMxQyxNQUFNLFNBQVMsR0FBRyxXQUFXLENBQUMsYUFBYSxDQUFDLHFFQUFxRSxDQUFDO0VBRWxILElBQUksQ0FBQyxTQUFTLEVBQUU7SUFDWjtFQUNKO0VBRUEsTUFBTSxVQUFVLEdBQUcsU0FBUyxDQUFDLFlBQVksQ0FBQyxtQ0FBbUMsQ0FBQztFQUU5RSxJQUFJLFVBQVUsQ0FBQyxJQUFJLENBQUMsU0FBUyxDQUFDLEtBQUssQ0FBQyxJQUFJLFNBQVMsQ0FBQyxJQUFJLENBQUMsVUFBVSxDQUFDLEVBQUU7SUFDaEUsTUFBTSxNQUFNLEdBQUcsVUFBVSxHQUFHLEdBQUcsR0FBRyxTQUFTLENBQUMsS0FBSyxHQUFHLHdDQUF3QztJQUU1RixRQUFRLENBQUMsTUFBTSxHQUFHLE1BQU0sQ0FBQyxRQUFRLENBQUMsUUFBUSxLQUFLLFFBQVEsR0FBRyxTQUFTLEdBQUcsTUFBTSxHQUFHLFVBQVUsR0FBRyxNQUFNO0VBQ3RHO0FBQ0o7O0FBRUE7QUFDQSxpRUFBZSw0QkFBNEIsRSIsInNvdXJjZXMiOlsid2VicGFjazovLy8uL2Fzc2V0cy9jb250cm9sbGVycy9jc3JmX3Byb3RlY3Rpb25fY29udHJvbGxlci5qcyJdLCJzb3VyY2VzQ29udGVudCI6WyJjb25zdCBuYW1lQ2hlY2sgPSAvXlstX2EtekEtWjAtOV17NCwyMn0kLztcbmNvbnN0IHRva2VuQ2hlY2sgPSAvXlstXy8rYS16QS1aMC05XXsyNCx9JC87XG5cbi8vIEdlbmVyYXRlIGFuZCBkb3VibGUtc3VibWl0IGEgQ1NSRiB0b2tlbiBpbiBhIGZvcm0gZmllbGQgYW5kIGEgY29va2llLCBhcyBkZWZpbmVkIGJ5IFN5bWZvbnkncyBTYW1lT3JpZ2luQ3NyZlRva2VuTWFuYWdlclxuLy8gVXNlIGBmb3JtLnJlcXVlc3RTdWJtaXQoKWAgdG8gZW5zdXJlIHRoYXQgdGhlIHN1Ym1pdCBldmVudCBpcyB0cmlnZ2VyZWQuIFVzaW5nIGBmb3JtLnN1Ym1pdCgpYCB3aWxsIG5vdCB0cmlnZ2VyIHRoZSBldmVudFxuLy8gYW5kIHRodXMgdGhpcyBldmVudC1saXN0ZW5lciB3aWxsIG5vdCBiZSBleGVjdXRlZC5cbmRvY3VtZW50LmFkZEV2ZW50TGlzdGVuZXIoJ3N1Ym1pdCcsIGZ1bmN0aW9uIChldmVudCkge1xuICAgIGdlbmVyYXRlQ3NyZlRva2VuKGV2ZW50LnRhcmdldCk7XG59LCB0cnVlKTtcblxuLy8gV2hlbiBAaG90d2lyZWQvdHVyYm8gaGFuZGxlcyBmb3JtIHN1Ym1pc3Npb25zLCBzZW5kIHRoZSBDU1JGIHRva2VuIGluIGEgaGVhZGVyIGluIGFkZGl0aW9uIHRvIGEgY29va2llXG4vLyBUaGUgYGZyYW1ld29yay5jc3JmX3Byb3RlY3Rpb24uY2hlY2tfaGVhZGVyYCBjb25maWcgb3B0aW9uIG5lZWRzIHRvIGJlIGVuYWJsZWQgZm9yIHRoZSBoZWFkZXIgdG8gYmUgY2hlY2tlZFxuZG9jdW1lbnQuYWRkRXZlbnRMaXN0ZW5lcigndHVyYm86c3VibWl0LXN0YXJ0JywgZnVuY3Rpb24gKGV2ZW50KSB7XG4gICAgY29uc3QgaCA9IGdlbmVyYXRlQ3NyZkhlYWRlcnMoZXZlbnQuZGV0YWlsLmZvcm1TdWJtaXNzaW9uLmZvcm1FbGVtZW50KTtcbiAgICBPYmplY3Qua2V5cyhoKS5tYXAoZnVuY3Rpb24gKGspIHtcbiAgICAgICAgZXZlbnQuZGV0YWlsLmZvcm1TdWJtaXNzaW9uLmZldGNoUmVxdWVzdC5oZWFkZXJzW2tdID0gaFtrXTtcbiAgICB9KTtcbn0pO1xuXG4vLyBXaGVuIEBob3R3aXJlZC90dXJibyBoYW5kbGVzIGZvcm0gc3VibWlzc2lvbnMsIHJlbW92ZSB0aGUgQ1NSRiBjb29raWUgb25jZSBhIGZvcm0gaGFzIGJlZW4gc3VibWl0dGVkXG5kb2N1bWVudC5hZGRFdmVudExpc3RlbmVyKCd0dXJibzpzdWJtaXQtZW5kJywgZnVuY3Rpb24gKGV2ZW50KSB7XG4gICAgcmVtb3ZlQ3NyZlRva2VuKGV2ZW50LmRldGFpbC5mb3JtU3VibWlzc2lvbi5mb3JtRWxlbWVudCk7XG59KTtcblxuZXhwb3J0IGZ1bmN0aW9uIGdlbmVyYXRlQ3NyZlRva2VuIChmb3JtRWxlbWVudCkge1xuICAgIGNvbnN0IGNzcmZGaWVsZCA9IGZvcm1FbGVtZW50LnF1ZXJ5U2VsZWN0b3IoJ2lucHV0W2RhdGEtY29udHJvbGxlcj1cImNzcmYtcHJvdGVjdGlvblwiXSwgaW5wdXRbbmFtZT1cIl9jc3JmX3Rva2VuXCJdJyk7XG5cbiAgICBpZiAoIWNzcmZGaWVsZCkge1xuICAgICAgICByZXR1cm47XG4gICAgfVxuXG4gICAgbGV0IGNzcmZDb29raWUgPSBjc3JmRmllbGQuZ2V0QXR0cmlidXRlKCdkYXRhLWNzcmYtcHJvdGVjdGlvbi1jb29raWUtdmFsdWUnKTtcbiAgICBsZXQgY3NyZlRva2VuID0gY3NyZkZpZWxkLnZhbHVlO1xuXG4gICAgaWYgKCFjc3JmQ29va2llICYmIG5hbWVDaGVjay50ZXN0KGNzcmZUb2tlbikpIHtcbiAgICAgICAgY3NyZkZpZWxkLnNldEF0dHJpYnV0ZSgnZGF0YS1jc3JmLXByb3RlY3Rpb24tY29va2llLXZhbHVlJywgY3NyZkNvb2tpZSA9IGNzcmZUb2tlbik7XG4gICAgICAgIGNzcmZGaWVsZC5kZWZhdWx0VmFsdWUgPSBjc3JmVG9rZW4gPSBidG9hKFN0cmluZy5mcm9tQ2hhckNvZGUuYXBwbHkobnVsbCwgKHdpbmRvdy5jcnlwdG8gfHwgd2luZG93Lm1zQ3J5cHRvKS5nZXRSYW5kb21WYWx1ZXMobmV3IFVpbnQ4QXJyYXkoMTgpKSkpO1xuICAgIH1cbiAgICBjc3JmRmllbGQuZGlzcGF0Y2hFdmVudChuZXcgRXZlbnQoJ2NoYW5nZScsIHsgYnViYmxlczogdHJ1ZSB9KSk7XG5cbiAgICBpZiAoY3NyZkNvb2tpZSAmJiB0b2tlbkNoZWNrLnRlc3QoY3NyZlRva2VuKSkge1xuICAgICAgICBjb25zdCBjb29raWUgPSBjc3JmQ29va2llICsgJ18nICsgY3NyZlRva2VuICsgJz0nICsgY3NyZkNvb2tpZSArICc7IHBhdGg9Lzsgc2FtZXNpdGU9c3RyaWN0JztcbiAgICAgICAgZG9jdW1lbnQuY29va2llID0gd2luZG93LmxvY2F0aW9uLnByb3RvY29sID09PSAnaHR0cHM6JyA/ICdfX0hvc3QtJyArIGNvb2tpZSArICc7IHNlY3VyZScgOiBjb29raWU7XG4gICAgfVxufVxuXG5leHBvcnQgZnVuY3Rpb24gZ2VuZXJhdGVDc3JmSGVhZGVycyAoZm9ybUVsZW1lbnQpIHtcbiAgICBjb25zdCBoZWFkZXJzID0ge307XG4gICAgY29uc3QgY3NyZkZpZWxkID0gZm9ybUVsZW1lbnQucXVlcnlTZWxlY3RvcignaW5wdXRbZGF0YS1jb250cm9sbGVyPVwiY3NyZi1wcm90ZWN0aW9uXCJdLCBpbnB1dFtuYW1lPVwiX2NzcmZfdG9rZW5cIl0nKTtcblxuICAgIGlmICghY3NyZkZpZWxkKSB7XG4gICAgICAgIHJldHVybiBoZWFkZXJzO1xuICAgIH1cblxuICAgIGNvbnN0IGNzcmZDb29raWUgPSBjc3JmRmllbGQuZ2V0QXR0cmlidXRlKCdkYXRhLWNzcmYtcHJvdGVjdGlvbi1jb29raWUtdmFsdWUnKTtcblxuICAgIGlmICh0b2tlbkNoZWNrLnRlc3QoY3NyZkZpZWxkLnZhbHVlKSAmJiBuYW1lQ2hlY2sudGVzdChjc3JmQ29va2llKSkge1xuICAgICAgICBoZWFkZXJzW2NzcmZDb29raWVdID0gY3NyZkZpZWxkLnZhbHVlO1xuICAgIH1cblxuICAgIHJldHVybiBoZWFkZXJzO1xufVxuXG5leHBvcnQgZnVuY3Rpb24gcmVtb3ZlQ3NyZlRva2VuIChmb3JtRWxlbWVudCkge1xuICAgIGNvbnN0IGNzcmZGaWVsZCA9IGZvcm1FbGVtZW50LnF1ZXJ5U2VsZWN0b3IoJ2lucHV0W2RhdGEtY29udHJvbGxlcj1cImNzcmYtcHJvdGVjdGlvblwiXSwgaW5wdXRbbmFtZT1cIl9jc3JmX3Rva2VuXCJdJyk7XG5cbiAgICBpZiAoIWNzcmZGaWVsZCkge1xuICAgICAgICByZXR1cm47XG4gICAgfVxuXG4gICAgY29uc3QgY3NyZkNvb2tpZSA9IGNzcmZGaWVsZC5nZXRBdHRyaWJ1dGUoJ2RhdGEtY3NyZi1wcm90ZWN0aW9uLWNvb2tpZS12YWx1ZScpO1xuXG4gICAgaWYgKHRva2VuQ2hlY2sudGVzdChjc3JmRmllbGQudmFsdWUpICYmIG5hbWVDaGVjay50ZXN0KGNzcmZDb29raWUpKSB7XG4gICAgICAgIGNvbnN0IGNvb2tpZSA9IGNzcmZDb29raWUgKyAnXycgKyBjc3JmRmllbGQudmFsdWUgKyAnPTA7IHBhdGg9Lzsgc2FtZXNpdGU9c3RyaWN0OyBtYXgtYWdlPTAnO1xuXG4gICAgICAgIGRvY3VtZW50LmNvb2tpZSA9IHdpbmRvdy5sb2NhdGlvbi5wcm90b2NvbCA9PT0gJ2h0dHBzOicgPyAnX19Ib3N0LScgKyBjb29raWUgKyAnOyBzZWN1cmUnIDogY29va2llO1xuICAgIH1cbn1cblxuLyogc3RpbXVsdXNGZXRjaDogJ2xhenknICovXG5leHBvcnQgZGVmYXVsdCAnY3NyZi1wcm90ZWN0aW9uLWNvbnRyb2xsZXInO1xuIl0sIm5hbWVzIjpbXSwic291cmNlUm9vdCI6IiJ9