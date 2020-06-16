<!--
  - @copyright Copyright (c) 2020 Jan C. Borchardt https://jancborchardt.net
  -
  - @author Jan C. Borchardt https://jancborchardt.net
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div class="section question">
		<h3>{{ question.text }}</h3>
		<p class="question-detail">
			{{ questionType }}
		</p>
		<ol v-if="question.type == 'multiple' || question.type == 'multiple_unique'" class="question-multiple">
			<li v-for="questionOption in questionOptions"
				:key="questionOption.id">
				<label :for="questionOption.text.replace(/\s+/g, '')">
					{{ questionOption.percentage }}%, {{ questionOption.count }}:
					<span>{{ questionOption.text }}</span>
				</label>
				<meter :id="questionOption.text.replace(/\s+/g, '')"
					min="0"
					:max="submissions.length"
					:value="questionOption.count" />
			</li>
		</ol>
		<ul v-if="question.type == 'short' || question.type == 'long'" class="question-text">
			<li v-for="textAnswer in textAnswers"
				:key="textAnswer.id">
				{{ textAnswer }}
			</li>
		</ul>
	</div>
</template>

<script>
export default {
	name: 'Summary',

	components: {
	},

	props: {
		submissions: {
			type: Array,
			required: true,
		},
		question: {
			type: Object,
			required: true,
		},
	},

	computed: {
		questionType() {
			if (this.question.type === 'multiple_unique') {
				return t('forms', 'Multiple choice')
			}
			if (this.question.type === 'multiple') {
				return t('forms', 'Checkboxes')
			}
			if (this.question.type === 'short') {
				return t('forms', 'Short answer')
			}
			if (this.question.type === 'long') {
				return t('forms', 'Long text')
			}
			return ''
		},

		// For countable questions like multiple choice and checkboxes
		questionOptions() {
			const questionOptionsStats = []

			// Also record 'No response'
			questionOptionsStats.push({
				'text': t('forms', 'No response'),
				'count': 0,
				'percentage': 0,
			})

			// Build list of question options
			this.question.options.forEach(option => {
				questionOptionsStats.push({
					'text': option.text,
					'count': 0,
					'percentage': 0,
				})
			})

			// Go through submissions to check which options have how many responses
			this.submissions.forEach(submission => {
				const answers = submission.answers.filter(answer => answer.questionId === this.question.id)
				if (!answers.length) {
					// Record 'No response'
					questionOptionsStats[0].count++
				}

				// Check question options to find which needs to be increased
				answers.forEach(answer => {
					for (const i in questionOptionsStats) {
						if (questionOptionsStats[i].text === answer.text) {
							questionOptionsStats[i].count++
							break
						}
					}
				})
			})

			// Sort options by response count
			function compare(object1, object2) {
				if (object1.count < object2.count) {
					return 1
				}
				if (object1.count > object2.count) {
					return -1
				}
				return 0
			}

			questionOptionsStats.sort(compare)

			// Fill percentage values
			for (const i in questionOptionsStats) {
				questionOptionsStats[i].percentage = Math.round((100 * questionOptionsStats[i].count) / this.submissions.length)
			}

			return questionOptionsStats
		},

		// For text answers like short answer and long text
		textAnswers() {
			const textAnswers = []

			// Also record 'No response'
			textAnswers[0] = 0

			// Go through submissions to check which options have how many responses
			this.submissions.forEach(submission => {
				const answers = submission.answers.filter(answer => answer.questionId === this.question.id)
				if (!answers.length) {
					// Record 'No response'
					textAnswers[0]++
				}

				// Add text answers
				answers.forEach(answer => {
					textAnswers.push(answer.text)
				})
			})

			// Calculate no response percentage
			const noResponseCount = textAnswers[0]
			const noResponsePercentage = Math.round((100 * textAnswers[0]) / this.submissions.length)
			textAnswers[0] = noResponsePercentage + '%, ' + noResponseCount + ': ' + t('forms', 'No response')

			return textAnswers
		},
	},
}
</script>

<style lang="scss" scoped>
.question {
	padding-left: 16px;
	padding-right: 16px;

	h3 {
		font-weight: bold;
	}

	&-detail {
		color: var(--color-text-lighter);
		margin-top: -8px;
	}

	ol {
		list-style-type: none;
	}

	ul,
	ol {
		margin-top: 8px;

		&.question-text {
			list-style-type: initial;

			li {
				padding: 4px 0;
			}
		}

		&.question-multiple {
			li {
				position: relative;
				padding: 8px 0;

				&:first-child span {
					font-weight: bold;
				}

				meter {
					display: block;
					width: 100%;
					margin-top: 4px;
					background: var(--color-background-dark);
					height: calc(var(--border-radius) * 2);
					border-radius: var(--border-radius);

					&::-webkit-meter-optimum-value,
					&::-moz-meter-bar {
						background: linear-gradient(40deg, var(--color-primary-element) 0%, var(--color-primary-element-light) 100%);
						border-radius: var(--border-radius);
						-webkit-transition: background-color .3s ease;
						transition: background-color .3s ease;
					}
				}

				&:hover meter::-webkit-meter-optimum-value,
				&:hover meter::-moz-meter-bar,
				&:focus meter::-webkit-meter-optimum-value,
				&:focus meter::-moz-meter-bar, {
					animation: percentage-animation 1s linear infinite;
					background: linear-gradient(40deg, var(--color-primary-element), var(--color-primary-element-light) 33%, var(--color-primary-element) 67%, var(--color-primary-element-light));
					background-size: 300% 100%;
				}
			}
		}
	}

	@keyframes percentage-animation {
		0% {
			background-position: 100%;
		}
		to {
			background-position: 0;
		}
	}
}
</style>
