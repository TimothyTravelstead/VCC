			<div class="CallType">
				<span><input type="checkbox" name="COMEOUT" value="1"/>Quest/Com. Out</span>
				<span><input type="checkbox" name="AIDS_HIV" value="1" />Safer Sex</span>
				<span><input type="checkbox" name="TGENDER" value="1"/>Gender Ident/Exp</span>
				<span><input type="checkbox" name="RELATION" value="1"/>Relationships</span>
				<span><input type="checkbox" name="PARENT" value="1"/>Parent</span>
				<span><input type="checkbox" name="SUICIDE" value="1"/>Suicide</span>

				<span><input type="checkbox" name="RUNAWAY" value="1"/>Runaway</span>
				<span><input type="checkbox" name="VIOLENCE" value="1"/>Violence</span>
				<span><input type="checkbox" name="SELF_EST" value="1"/>Self Esteem</span>
				<span><input type="checkbox" name="OTHER" value="1"/>Other</span>
			</div>
			<div class="ResourceCategories">
				<span><input type="checkbox" name="AIDS" value="1" />AIDS</span>
				<span><input type="checkbox" name="BARS" value="1" />Bars/Clubs</span>
				<span><input type="checkbox" name="BISEXUAL" value="1" />Bisexual</span>
				<span><input type="checkbox" name="BOOKSTORE" value="1" />Bookstore</span>
				<span><input type="checkbox" name="BUSINESS" value="1" />Business</span>
				<span><input type="checkbox" name="COMMUNITY" value="1" />Comm. Cntrs</span>
				<span><input type="checkbox" name="COUNSELING" value="1" />Counseling</span>

				<span><input type="checkbox" name="CRISIS" value="1" />Crisis</span>
				<span><input type="checkbox" name="CULTURAL" value="1" />Cultural</span>
				<span><input type="checkbox" name="FUNDRAISE" value="1" />Fundraising</span>
				<span><input type="checkbox" name="HEALTH" value="1" />Health</span>
				<span><input type="checkbox" name="HOTEL" value="1" />Hotels</span>
				<span><input type="checkbox" name="HOTLINE" value="1" />Hotlines</span>

				<span><input type="checkbox" name="LEGAL" value="1" />Legal</span>
				<span><input type="checkbox" name="LESBIAN" value="1" />Lesbian</span>
				<span><input type="checkbox" name="MEDIA" value="1" />Media</span>
				<span><input type="checkbox" name="POLITICAL" value="1" />Political</span>
				<span><input type="checkbox" name="PROFESSIONAL" value="1" />Professional</span>
				<span><input type="checkbox" name="RECOVERY" value="1" />Recovery</span>
				<span><input type="checkbox" name="RELIGION" value="1" />Religion</span>
				<span><input type="checkbox" name="RESTAURANT" value="1" />Restaurant</span>

				<span><input id="seniorCategoryCheckbox" type="checkbox" name="SENIOR" value="1" />Senior</span>
				<span><input type="checkbox" name="SOCIAL" value="1" />Social</span>
				<span><input type="checkbox" name="SPORTS" value="1" />Sports</span>
				<span><input type="checkbox" name="STUDENT" value="1" />Student</span>
				<span><input type="checkbox" name="SUPPORT" value="1" />Support</span>
				<span><input type="checkbox" name="TRANSGENDER" value="1" />Transgender</span>
				<span><input type="checkbox" name="YOUTH" value="1" />Youth</span>
			</div>
			<div id="nonSageDiscoveredCategories" class = "DiscoveredCategories">
				<span><input type="radio" name="GOT_NUMBER" value="Internet" />Internet</span>
				<span><input type="radio" name="GOT_NUMBER" value="Friend/Relative" />Friend/Relative</span>
				<span><input type="radio" name="GOT_NUMBER" value="Other Group" />Other Group</span>
				<span><input type="radio" name="GOT_NUMBER" value="School/Teacher" />School/Teacher</span>
				<span><input type="radio" name="GOT_NUMBER" value="Phone Book" />Phone Book</span>
				<span><input type="radio" name="GOT_NUMBER" value="Direct Asst" />Directory Assist.</span>

				<span><input type="radio" name="GOT_NUMBER" value="Advertisement/Poster" />Advert./Poster</span>
				<span><input type="radio" name="GOT_NUMBER" value="Gay Media" />Gay Media</span>
				<span><input type="radio" name="GOT_NUMBER" value="Straight Media" />Straight Media</span>
				<span><input type="radio" name="GOT_NUMBER" value="CDC/AIDS Hotline" />AIDS Hotline</span>
				<span><input type="radio" name="GOT_NUMBER" value="Other Hotline" />Other Hotline</span>
				<span><input type="radio" name="GOT_NUMBER" value="Unknown" />Unknown</span>
			</div>
			<div id="NuisanceButtons">
				<input type="button" id="NuisanceButton" value="Nuisance" onclick="javascript:SaveLog(this.parentNode.parentNode.parentNode.id, this.value)"/> 
				<input type="button" id="HangupButton" value="Hangup" onclick="javascript:SaveLog(this.parentNode.parentNode.parentNode.id, this.value)"/> 
				<input type="button" id="AbusiveButton" value="Abusive" onclick="javascript:SaveLog(this.parentNode.parentNode.parentNode.id, this.value)"/> 
			</div>

			<div class="OtherCallLogData">
				<table>
					<tr><th>Gender:</th>
					<td><select id='callLogGenderMenu' name="GENDER">
						<option value=""></option>
						<option value="Male">Male</option>
						<option value="Female">Female</option>
						<option value="All Others">Non-Binary</option>
						<option value="Questioning">Questioning</option>
					</select></td></tr>
				
					<tr id="nonSageAge"><th>Age:</th>
					<td><select name="AGE">
						<option value=""></option>
						<option value="12 and Under">12 and Under</option>
						<option value="13-19">13-19</option>
						<option value="20-29">20-29</option>
						<option value="30-39">30-39</option>
						<option value="40-49">40-49</option>
						<option value="50-54">50-54</option>
						<option value="55-59">55-59</option>
						<option value="60-69">60-69</option>
						<option value="70 and Over">70 and Over</option>
					</select></td></tr>

					<tr><th>Call Quality:</th>
					<td><select id="VolunterRating" name="VolunteerRating">
						<option value=""></option>
						<option value="Good">Good</option>
						<option value="Neutral">Neutral</option>
						<option value="Bad">Bad</option>
					</select></td></tr>

					<tr><th>Hotline:</th>
					<td><select name="GLBTNHC_Program" id="GLBTNHC_Program">
						<option value=""></option>
						<option value="GLNH">GLNH</option>
						<option value="Youth Talkline">Youth Talkline</option>
						<option value="GLSB NY">GLSB NY</option>
						<option value="Chat">Chat</option>
						<option value="Senior">Senior</option>
						<option value="OUT">Coming Out</option>
					</select></td></tr>

					<tr><th>Country:</th>
					<td><select name="callHistoryCountry" id="callHistoryCountry">
						<option value="USA" selected="selected">U.S.</option>
						<option value="Afghanistan">Afghanistan</option> 
						<option value="Albania">Albania</option> 
						<option value="Algeria">Algeria</option> 
						<option value="American Samoa">American Samoa</option> 
						<option value="Andorra">Andorra</option> 
						<option value="Angola">Angola</option> 
						<option value="Anguilla">Anguilla</option> 
						<option value="Antarctica">Antarctica</option> 
						<option value="Antigua and Barbuda">Antigua and Barbuda</option> 
						<option value="Argentina">Argentina</option> 
						<option value="Armenia">Armenia</option> 
						<option value="Aruba">Aruba</option> 
						<option value="Australia">Australia</option> 
						<option value="Austria">Austria</option> 
						<option value="Azerbaijan">Azerbaijan</option> 
						<option value="Bahamas">Bahamas</option> 
						<option value="Bahrain">Bahrain</option> 
						<option value="Bangladesh">Bangladesh</option> 
						<option value="Barbados">Barbados</option> 
						<option value="Belarus">Belarus</option> 
						<option value="Belgium">Belgium</option> 
						<option value="Belize">Belize</option> 
						<option value="Benin">Benin</option> 
						<option value="Bermuda">Bermuda</option> 
						<option value="Bhutan">Bhutan</option> 
						<option value="Bolivia">Bolivia</option> 
						<option value="Bosnia and Herzegovina">Bosnia and Herzegovina</option> 
						<option value="Botswana">Botswana</option> 
						<option value="Bouvet Island">Bouvet Island</option> 
						<option value="Brazil">Brazil</option> 
						<option value="British Indian Ocean Territory">British Indian Ocean Territory</option> 
						<option value="Brunei Darussalam">Brunei Darussalam</option> 
						<option value="Bulgaria">Bulgaria</option> 
						<option value="Burkina Faso">Burkina Faso</option> 
						<option value="Burundi">Burundi</option> 
						<option value="Cambodia">Cambodia</option> 
						<option value="Cameroon">Cameroon</option> 
						<option value="Canada">Canada</option> 
						<option value="Cape Verde">Cape Verde</option> 
						<option value="Cayman Islands">Cayman Islands</option> 
						<option value="Central African Republic">Central African Republic</option> 
						<option value="Chad">Chad</option> 
						<option value="Chile">Chile</option> 
						<option value="China">China</option> 
						<option value="Christmas Island">Christmas Island</option> 
						<option value="Cocos (Keeling) Islands">Cocos (Keeling) Islands</option> 
						<option value="Colombia">Colombia</option> 
						<option value="Comoros">Comoros</option> 
						<option value="Congo">Congo</option> 
						<option value="Congo, The Democratic Republic of The">Congo, The Democratic Republic of The</option> 
						<option value="Cook Islands">Cook Islands</option> 
						<option value="Costa Rica">Costa Rica</option> 
						<option value="Cote D'ivoire">Cote D'ivoire</option> 
						<option value="Croatia">Croatia</option> 
						<option value="Cuba">Cuba</option> 
						<option value="Cyprus">Cyprus</option> 
						<option value="Czech Republic">Czech Republic</option> 
						<option value="Denmark">Denmark</option> 
						<option value="Djibouti">Djibouti</option> 
						<option value="Dominica">Dominica</option> 
						<option value="Dominican Republic">Dominican Republic</option> 
						<option value="Ecuador">Ecuador</option> 
						<option value="Egypt">Egypt</option> 
						<option value="El Salvador">El Salvador</option> 
						<option value="Equatorial Guinea">Equatorial Guinea</option> 
						<option value="Eritrea">Eritrea</option> 
						<option value="Estonia">Estonia</option> 
						<option value="Ethiopia">Ethiopia</option> 
						<option value="Falkland Islands (Malvinas)">Falkland Islands (Malvinas)</option> 
						<option value="Faroe Islands">Faroe Islands</option> 
						<option value="Fiji">Fiji</option> 
						<option value="Finland">Finland</option> 
						<option value="France">France</option> 
						<option value="French Guiana">French Guiana</option> 
						<option value="French Polynesia">French Polynesia</option> 
						<option value="French Southern Territories">French Southern Territories</option> 
						<option value="Gabon">Gabon</option> 
						<option value="Gambia">Gambia</option> 
						<option value="Georgia">Georgia</option> 
						<option value="Germany">Germany</option> 
						<option value="Ghana">Ghana</option> 
						<option value="Gibraltar">Gibraltar</option> 
						<option value="Greece">Greece</option> 
						<option value="Greenland">Greenland</option> 
						<option value="Grenada">Grenada</option> 
						<option value="Guadeloupe">Guadeloupe</option> 
						<option value="Guam">Guam</option> 
						<option value="Guatemala">Guatemala</option> 
						<option value="Guinea">Guinea</option> 
						<option value="Guinea-bissau">Guinea-bissau</option> 
						<option value="Guyana">Guyana</option> 
						<option value="Haiti">Haiti</option> 
						<option value="Heard Island and Mcdonald Islands">Heard Island and Mcdonald Islands</option> 
						<option value="Holy See (Vatican City State)">Holy See (Vatican City State)</option> 
						<option value="Honduras">Honduras</option> 
						<option value="Hong Kong">Hong Kong</option> 
						<option value="Hungary">Hungary</option> 
						<option value="Iceland">Iceland</option> 
						<option value="India">India</option> 
						<option value="Indonesia">Indonesia</option> 
						<option value="Iran, Islamic Republic of">Iran, Islamic Republic of</option> 
						<option value="Iraq">Iraq</option> 
						<option value="Ireland">Ireland</option> 
						<option value="Israel">Israel</option> 
						<option value="Italy">Italy</option> 
						<option value="Jamaica">Jamaica</option> 
						<option value="Japan">Japan</option> 
						<option value="Jordan">Jordan</option> 
						<option value="Kazakhstan">Kazakhstan</option> 
						<option value="Kenya">Kenya</option> 
						<option value="Kiribati">Kiribati</option> 
						<option value="Korea, Democratic People's Republic of">Korea, Democratic People's Republic of</option> 
						<option value="Korea, Republic of">Korea, Republic of</option> 
						<option value="Kuwait">Kuwait</option> 
						<option value="Kyrgyzstan">Kyrgyzstan</option> 
						<option value="Lao People's Democratic Republic">Lao People's Democratic Republic</option> 
						<option value="Latvia">Latvia</option> 
						<option value="Lebanon">Lebanon</option> 
						<option value="Lesotho">Lesotho</option> 
						<option value="Liberia">Liberia</option> 
						<option value="Libyan Arab Jamahiriya">Libyan Arab Jamahiriya</option> 
						<option value="Liechtenstein">Liechtenstein</option> 
						<option value="Lithuania">Lithuania</option> 
						<option value="Luxembourg">Luxembourg</option> 
						<option value="Macao">Macao</option> 
						<option value="Macedonia, The Former Yugoslav Republic of">Macedonia, The Former Yugoslav Republic of</option> 
						<option value="Madagascar">Madagascar</option> 
						<option value="Malawi">Malawi</option> 
						<option value="Malaysia">Malaysia</option> 
						<option value="Maldives">Maldives</option> 
						<option value="Mali">Mali</option> 
						<option value="Malta">Malta</option> 
						<option value="Marshall Islands">Marshall Islands</option> 
						<option value="Martinique">Martinique</option> 
						<option value="Mauritania">Mauritania</option> 
						<option value="Mauritius">Mauritius</option> 
						<option value="Mayotte">Mayotte</option> 
						<option value="Mexico">Mexico</option> 
						<option value="Micronesia, Federated States of">Micronesia, Federated States of</option> 
						<option value="Moldova, Republic of">Moldova, Republic of</option> 
						<option value="Monaco">Monaco</option> 
						<option value="Mongolia">Mongolia</option> 
						<option value="Montserrat">Montserrat</option> 
						<option value="Morocco">Morocco</option> 
						<option value="Mozambique">Mozambique</option> 
						<option value="Myanmar">Myanmar</option> 
						<option value="Namibia">Namibia</option> 
						<option value="Nauru">Nauru</option> 
						<option value="Nepal">Nepal</option> 
						<option value="Netherlands">Netherlands</option> 
						<option value="Netherlands Antilles">Netherlands Antilles</option> 
						<option value="New Caledonia">New Caledonia</option> 
						<option value="New Zealand">New Zealand</option> 
						<option value="Nicaragua">Nicaragua</option> 
						<option value="Niger">Niger</option> 
						<option value="Nigeria">Nigeria</option> 
						<option value="Niue">Niue</option> 
						<option value="Norfolk Island">Norfolk Island</option> 
						<option value="Northern Mariana Islands">Northern Mariana Islands</option> 
						<option value="Norway">Norway</option> 
						<option value="Oman">Oman</option> 
						<option value="Pakistan">Pakistan</option> 
						<option value="Palau">Palau</option> 
						<option value="Palestinian Territory, Occupied">Palestinian Territory, Occupied</option> 
						<option value="Panama">Panama</option> 
						<option value="Papua New Guinea">Papua New Guinea</option> 
						<option value="Paraguay">Paraguay</option> 
						<option value="Peru">Peru</option> 
						<option value="Philippines">Philippines</option> 
						<option value="Pitcairn">Pitcairn</option> 
						<option value="Poland">Poland</option> 
						<option value="Portugal">Portugal</option> 
						<option value="Puerto Rico">Puerto Rico</option> 
						<option value="Qatar">Qatar</option> 
						<option value="Reunion">Reunion</option> 
						<option value="Romania">Romania</option> 
						<option value="Russian Federation">Russian Federation</option> 
						<option value="Rwanda">Rwanda</option> 
						<option value="Saint Helena">Saint Helena</option> 
						<option value="Saint Kitts and Nevis">Saint Kitts and Nevis</option> 
						<option value="Saint Lucia">Saint Lucia</option> 
						<option value="Saint Pierre and Miquelon">Saint Pierre and Miquelon</option> 
						<option value="Saint Vincent and The Grenadines">Saint Vincent and The Grenadines</option> 
						<option value="Samoa">Samoa</option> 
						<option value="San Marino">San Marino</option> 
						<option value="Sao Tome and Principe">Sao Tome and Principe</option> 
						<option value="Saudi Arabia">Saudi Arabia</option> 
						<option value="Senegal">Senegal</option> 
						<option value="Serbia and Montenegro">Serbia and Montenegro</option> 
						<option value="Seychelles">Seychelles</option> 
						<option value="Sierra Leone">Sierra Leone</option> 
						<option value="Singapore">Singapore</option> 
						<option value="Slovakia">Slovakia</option> 
						<option value="Slovenia">Slovenia</option> 
						<option value="Solomon Islands">Solomon Islands</option> 
						<option value="Somalia">Somalia</option> 
						<option value="South Africa">South Africa</option> 
						<option value="South Georgia and The South Sandwich Islands">South Georgia and The South Sandwich Islands</option> 
						<option value="Spain">Spain</option> 
						<option value="Sri Lanka">Sri Lanka</option> 
						<option value="Sudan">Sudan</option> 
						<option value="Suriname">Suriname</option> 
						<option value="Svalbard and Jan Mayen">Svalbard and Jan Mayen</option> 
						<option value="Swaziland">Swaziland</option> 
						<option value="Sweden">Sweden</option> 
						<option value="Switzerland">Switzerland</option> 
						<option value="Syrian Arab Republic">Syrian Arab Republic</option> 
						<option value="Taiwan, Province of China">Taiwan, Province of China</option> 
						<option value="Tajikistan">Tajikistan</option> 
						<option value="Tanzania, United Republic of">Tanzania, United Republic of</option> 
						<option value="Thailand">Thailand</option> 
						<option value="Timor-leste">Timor-leste</option> 
						<option value="Togo">Togo</option> 
						<option value="Tokelau">Tokelau</option> 
						<option value="Tonga">Tonga</option> 
						<option value="Trinidad and Tobago">Trinidad and Tobago</option> 
						<option value="Tunisia">Tunisia</option> 
						<option value="Turkey">Turkey</option> 
						<option value="Turkmenistan">Turkmenistan</option> 
						<option value="Turks and Caicos Islands">Turks and Caicos Islands</option> 
						<option value="Tuvalu">Tuvalu</option> 
						<option value="Uganda">Uganda</option> 
						<option value="Ukraine">Ukraine</option> 
						<option value="United Arab Emirates">United Arab Emirates</option> 
						<option value="United Kingdom">United Kingdom</option> 
						<option value="United States">United States</option> 
						<option value="United States Minor Outlying Islands">United States Minor Outlying Islands</option> 
						<option value="Uruguay">Uruguay</option> 
						<option value="Uzbekistan">Uzbekistan</option> 
						<option value="Vanuatu">Vanuatu</option> 
						<option value="Venezuela">Venezuela</option> 
						<option value="Viet Nam">Viet Nam</option> 
						<option value="Virgin Islands, British">Virgin Islands, British</option> 
						<option value="Virgin Islands, U.S.">Virgin Islands, U.S.</option> 
						<option value="Wallis and Futuna">Wallis and Futuna</option> 
						<option value="Western Sahara">Western Sahara</option> 
						<option value="Yemen">Yemen</option> 
						<option value="Zambia">Zambia</option> 
						<option value="Zimbabwe">Zimbabwe</option>
						<option value="Other">Other</option>
					</select></td></tr>
				</table>
				<input id='callLogID' name='callLogID' type='hidden' value="" />
				<input id='logCity' name='logCity' type='hidden' value="" />
				<input id='logState' name='logState' type='hidden' value="" />
				<input id='logZip' name='logZip' type='hidden' value="" />
			</div>
			<div id = 'logPaneNotes'>
				<label> <strong> Call Notes:</strong> </label><textarea name='CallLogNotes' maxlength="152" id='logTextArea'></textarea>
			</div>

			<div id="EndLogButtons">
				<input type="button" id="callLogCancelButton" value="Cancel" onclick="javascript:alert(this.parentNode.parentNode.parentNode.id)"/>
				<input type="button" id="callLogSaveButton" class="defaultButton" value="SAVE" onclick="javascript:alert(this.parentNode.parentNode.parentNode.id)"/>
			</div>
		










