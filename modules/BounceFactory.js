define([], () => {

 // Adapted from https://medium.com/hackernoon/the-bounce-factory-3498de1e5262 published by William Silversmith
/*
 * Interpolate between a start and end position.
 *
 * obj.x represents a position parameter (e.g. 12.2)
 * end_pos is the value obj.x will have at the end of the animation
 * msec is the number of milliseconds we want to run the animation for
 * easing is a timing function that accepts a number between 0 to 1 
 *    and returns the proportion of the interpolation between start and end to move the object to. 
 */
function animateAsync(args = {}) {
	return new Promise(resolve => {
		const {
			from,
			to,
			easing = t => t, // default to linear easing
			duration = 1000,
			fnApply,
		} = args;

		// performance.now is guaranteed to increase and gives sub-millisecond resolution
		// Date.now is susceptible to system clock changes and gives some number of milliseconds resolution
		const start = window.performance.now();
		const delta = to - from;

		function frame () {
			const now = window.performance.now();
			const t = (now - start) / duration; // normalize to 0..1

			if (t >= 1) { // if animation complete or running over
				fnApply(to);
				resolve();
				return;
			}

			const proportion = easing(t);
			fnApply(from + proportion * delta);
			requestAnimationFrame(frame);
		}

		requestAnimationFrame(frame);
	});
}

function clamp(x, min, max) {
    return Math.min(Math.max(x, min), max);
}

function bounceFactory(bounces, threshold) {
	threshold = threshold || 0.001;

	function energy_to_height (energy) {
		return energy; // h = E/mg
	}

	function height_to_energy (height) {
		return height; // E = mgh
	}

	function bounce_time (height) {
		return 2 * Math.sqrt(2 * height); // 2 x the half bounce time measured from the peak
	}

	function speed (energy) {
		return Math.sqrt(2 * energy); // E = 1/2 m v^2, s = |sqrt(2E/m)|
	}

	var height = 1;
	var potential = height_to_energy(height);

	var elasticity = Math.pow(threshold, 1 / bounces);

	// The critical points are the points where the object contacts the "ground"
	// Since the object is initially suspended at 1 height, this either creates an
	// exception for the following code, or you can use the following trick of placing
	// a critical point behind 0 and representing the inital position as halfway though
	// that arc.

	var critical_points = [{
		time: - bounce_time(height) / 2, 
		energy: potential,
	}, 
	{
		time: bounce_time(height) / 2,
		energy: potential * elasticity,
	}];

	potential *= elasticity;
	height = energy_to_height(potential);

	var time = critical_points[1].time;
	for (var i = 1; i < bounces; i++) {
		time += bounce_time(height);
		potential *= elasticity; // remove energy after each bounce

		critical_points.push({
			time: time,
			energy: potential,
		});

		height = energy_to_height(potential);
	}

	var duration = time; // renaming to emphasize it's the total time now

	return function (t) {
		t = clamp(t, 0, 1);

		var tadj = t * duration;

		if (tadj === 0) {
			return 0;
		}
		else if (tadj >= duration) {
			return 1;
		}

		// Find the bounce point we are bouncing from, for very long animations (hours, days),
		// an binary search algorithm might be appropriate.
		var index;
		for (index = 0; index < critical_points.length; index++) {
			if (critical_points[index].time > tadj) {
				break;
			}
		}

		var bouncept = critical_points[index - 1];

		// Bouncing from a bounce point effectively resets time as it is a discontinuity
		tadj -= bouncept.time; 

		var v0 = speed(bouncept.energy);

		// Project position of object from bounce point to the current time
		var pos = v0 * tadj + -0.5 * tadj * tadj;

		return 1 - pos;
	};
};

    return {
        async animateDropAsync(div, from, to, duration, easing) {
			const style = div.style;
			const transformRegex = /^(.*translate\([^,]+,\s*)(-?\d*(?:\.\d+)?)(em\).*)$/;
			const match = transformRegex.exec(style.transform);
			if (!match) throw new Error(`Unexpected transform: ${style.transform}`);
            await animateAsync({
                obj: div,
                from,
				to,
                duration,
                easing,
				fnApply: value => {
					style.transform = `${match[1]}${value}${match[3]}`;
				},
            });
        },
        bounceFactory,
    };
});