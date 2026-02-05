<form id="searchBox" name='searchBoxForm' class='redGradient' action='javascript:validate();'>
<h2>Search Parameters</h2>
	<table>
		<tr>
			<th class="label"><label for="IdNum">Id Number</label></th>
			<td>
				<input type="text" id="IdNum" tabindex="1" value="">
			</td>
		</tr>
		<tr>
			<th class="label"><label for="ZipCode">Zip Code</label></th>
			<td>
				<input type="text" class="search" id="ZipCode" tabindex="1" value="">
			</td>
		</tr> 
		<tr>
			<th class="label"><label for="Distance">Range</label></th>
			<td>
				<input type="text" class="search" id="Distance" tabindex="1" value="100">
			</td>
			<td>
			</td>
		</tr>
		<tr>
			<th class="label"><label for="City">City</label></th>
			<td>
				<input type="text" class="search" id="City" tabindex="1" value="">
			</td>
			<td>
				<input type="button" id="findZipSearch" value="Find Zip"">
			</td>
		</tr>
		<tr>
			<th class="label"><label for="State">State</label></th>
			<td>
				<input type="text" class="search" id="State" tabindex="1" value="">
			</td>
			<td>
				<input type="button" id="nationalSearch" value="National Listings" class="label">
			</td>
		</tr>
		<tr>
			<th class="label"></th>
			<td>
				<input type="hidden" class="search" id="County" tabindex="1" value="">
			</td>
			<td>
				<select name="countries" id="internationalSearch" aria-label="International Listings">
					<option value=" "> Int'l Listings</option>
				</select>
			</td>
		</tr>
		<tr>
			<th class="label"><label for="Name">Name</label></th>
			<td>
				<input type="text" id="Name" tabindex="1" value="">
			</td>
			<td>
				<input type="button" id="SendButton" value="Search" class="label" tabindex="2">
			</td>
		</tr>
		<tr>
			<th> </th>
		</tr>
		<tr>
			<th colspan='2'><h2>Date Range</h2></th>
		</tr>
		<tr>
			<th class="label"><label for="SearchFromDate">From</label></th>
			<td>
				<input type="text" class="label" id="SearchFromDate" tabindex="1" placeholder="yyyy-mm-dd">
			</td>
		</tr>
		<tr>
			<th class="label"><label for="SearchToDate">To:</label></th>
			<td>
				<input type="text" class="label" id="SearchToDate" tabindex="1" placeholder="yyyy-mm-dd">
			</td>
			<td>
				<input type="button" id="ProfilesButton" value="Profiles" class="label" tabindex="2">
			</td>
		</tr>
		<tr>
			<th class="label"></th>
			<td>
				<label for="confirmSaveToggle"><input type="checkbox" id="confirmSaveToggle" tabindex="99"> Confirm Save</label>
			</td>
		</tr>
	</table>
</form>