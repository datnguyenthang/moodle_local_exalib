// This file is part of Exabis Library
//
// (c) 2023 GTN - Global Training Network GmbH <office@gtn-solutions.com>
//
// Exabis Library is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This script is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You can find the GNU General Public License at <http://www.gnu.org/licenses/>.
//
// This copyright notice MUST APPEAR in all copies of the script!

$(function () {
	if ($('#exalib-categories').length) {
		var easytree = $('#exalib-categories').easytree();
	}
});

$(document).on('click', '.library-item', function (event) {
	if ($(event.target).closest('a, input').length) {
		// a link or button inside was pressed
		return;
	}

	// click the first link (header link)
	$(this).find('> a')[0].click();
	return;
});

// rating on edit page
$(function () {
	var fieldset = $('#fgroup_id_ratingarr fieldset');
	if (fieldset.length) {
		var starsContainer = $(''
			+ '<span style="cursor: pointer; font-size: 200%;">'
			+ '<span rating="1">&#9734;</span>'
			+ '<span rating="2">&#9734;</span>'
			+ '<span rating="3">&#9734;</span>'
			+ '<span rating="4">&#9734;</span>'
			+ '<span rating="5">&#9734;</span>'
			+ '</span>'
		);

		function draw_rating(rating) {
			starsContainer.children().each(function (i) {
				$(this).html((i + 1 <= rating) ? '&#9733;' : '&#9734;');
			});
		}
		function current_rating() {
			return fieldset.find(':radio:checked').val();
		}

		draw_rating(current_rating());

		starsContainer.children().click(function () {
			var newRating = $(this).attr('rating');
			if (current_rating() == newRating) {
				// same rating => unset
				newRating = 0;
			}
			fieldset.find(':radio[value=' + newRating + ']').prop('checked', true);
			draw_rating(newRating);
		});
		starsContainer.children().mouseover(function () {
			draw_rating($(this).attr('rating'));
		});
		starsContainer.children().mouseout(function () {
			draw_rating(current_rating());
		});

		// hide inputs
		fieldset.children().hide();

		// add stars
		fieldset.append(starsContainer);
	}
});