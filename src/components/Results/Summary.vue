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
	<div class="section question-summary">
		<h3>{{ question.text }}</h3>
		<p class="question-summary__detail">
			{{ answerTypes[question.type].label }}
		</p>
		<ol v-if="question.type == 'multiple' || question.type == 'multiple_unique' || question.type == 'dropdown'"
			class="question-summary__statistic">
			<li v-for="(questionOption, index) in questionOptions"
				:key="index">
				<label :for="questionOption.text.replace(/\s+/g, '')">
					{{ questionOption.count }} <span class="question-summary__statistic-percentage">({{ questionOption.percentage }}%):</span>
					<span class="question-summary__statistic-text">{{ questionOption.text }}</span>
				</label>
				<meter :id="questionOption.text.replace(/\s+/g, '')"
					min="0"
					:max="submissions.length"
					:value="questionOption.count" />
			</li>
		</ol>
		<ul v-else class="question-summary__text">
			<li v-for="(textAnswer, index) in textAnswers"
				:key="index">
				<template v-if="index === 0">
					<strong>{{ textAnswer }}</strong>
				</template>
				<template v-else>
					{{ textAnswer }}
				</template>
			</li>
		</ul>
	</div>
</template>

<script>
import answerTypes from '../../models/AnswerTypes'

export default {
	name: 'Summary',

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

	data() {
		return {
			answerTypes,
		}
	},

	computed: {
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
					const optionsStatIndex = questionOptionsStats.findIndex(option => option.text === answer.text)
					if (optionsStatIndex < 0) {
						questionOptionsStats.push({
							'text': answer.text,
							'count': 1,
							'percentage': 0,
						})
					} else {
						questionOptionsStats[optionsStatIndex].count++
					}
				})
			})

			// Sort options by response count
			questionOptionsStats.sort((object1, object2) => {
				if (object1.count === object2.count) {
					return 0
				}
				return object1.count < object2.count ? 1 : -1
			})

			// Fill percentage values
			questionOptionsStats.forEach(questionOptionsStat => {
				questionOptionsStat.percentage = Math.round((100 * questionOptionsStat.count) / this.submissions.length)
			})

			return questionOptionsStats
		},

		// For text answers like short answer and long text
		textAnswers() {
			const textAnswers = []

			// Also record 'No response'
			let noResponseCount = 0

			// Go through submissions to check which options have how many responses
			this.submissions.forEach(submission => {
				const answers = submission.answers.filter(answer => answer.questionId === this.question.id)
				if (!answers.length) {
					// Record 'No response'
					noResponseCount++
				}

				// Add text answers
				answers.forEach(answer => {
					textAnswers.push(answer.text)
				})
			})

			// Calculate no response percentage
			const noResponsePercentage = Math.round((100 * noResponseCount) / this.submissions.length)
			textAnswers.unshift(noResponseCount + ' (' + noResponsePercentage + '%): ' + t('forms', 'No response'))

			return textAnswers
		},
	},
}
</script>

<style lang="scss" scoped>
.question-summary {
	padding-left: 16px;
	padding-right: 16px;

	h3 {
		font-weight: bold;
	}

	&__detail {
		color: var(--color-text-lighter);
		margin-top: -8px;
	}

	ol {
		list-style-type: none;
	}

	ul,
	ol {
		margin-top: 8px;

		&__text {
			list-style-type: initial;

			li {
				padding: 4px 0;
			}
		}

		&__statistic {
			li {
				position: relative;
				padding: 8px 0;

				label {
					cursor: default;
				}

				&:first-child .question-summary__statistic-text {
					font-weight: bold;
				}

				.question-summary__statistic-percentage {
					color: var(--color-text-maxcontrast);
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
				&:focus meter::-moz-meter-bar {
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
