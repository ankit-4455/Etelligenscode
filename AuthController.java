package com.etelligens.ecommerce.auth.controller;

import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.authentication.AuthenticationManager;
import org.springframework.web.bind.annotation.CrossOrigin;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestHeader;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;

import com.etelligens.ecommerce.auth.dto.UserDTO;
import com.etelligens.ecommerce.auth.dto.UserResponseDTO;
import com.etelligens.ecommerce.auth.exception.LoginException;
import com.etelligens.ecommerce.auth.model.AuthenticationRequest;
import com.etelligens.ecommerce.auth.model.AuthenticationResponse;
import com.etelligens.ecommerce.auth.service.JwtUtil;
import com.etelligens.ecommerce.auth.service.UserService;
import com.etelligens.ecommerce.auth.service.ValidateService;

@CrossOrigin(origins = "*")
@RestController
@RequestMapping("/auth")
public class AuthController {

	@Autowired
	JwtUtil jwtUtil;

	@Autowired
	UserService userService;

	@Autowired
	ValidateService validateService;

	@Autowired
	private AuthenticationManager authenticationManager;

	/*
	 * @param userInfo - to add/register new user
	 */
	@PostMapping("/registration")
	public ResponseEntity<UserResponseDTO> addNewUser(@RequestBody UserDTO userInfo) throws LoginException {
		return new ResponseEntity<>(userService.addUser(userInfo), HttpStatus.OK);
	}

	/*
	 * @param authRequest - include UserName/email and password. Use to authenticate
	 * user.
	 */
	@PostMapping("/login")
	public ResponseEntity<UserResponseDTO> authenticateAndGetToken(@RequestBody AuthenticationRequest authRequest)
			throws LoginException {

		return new ResponseEntity<>(userService.login(authRequest), HttpStatus.OK);
	}

	/**
	 * @param token - to validate the token Sends the request header as
	 *              "Authorization" and returns the validity of token
	 */
	@GetMapping("/validate")
	public AuthenticationResponse getValidity(@RequestHeader("Authorization") final String token) {
		return validateService.validate(token);
	}
}
