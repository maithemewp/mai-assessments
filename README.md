# Mai Assessments
Assessment management, scores, and results via WP Forms and ACF Pro.

## Usage
* Build assessment forms with likert fields via WP Forms plugin.
* Visit Dashboard > WPForms > Assessment Results to configure form results settings and content.
* Use `[mai_assessment_results]` shortcode to display all assessment results on a single page.
* Use `[mai_assessment_results ids="456"]` or `[mai_assessment_results ids="456,123"]` to display results for one or more forms by ID.
* Use `[mai_assessment_score id="456"]` to display your score and level. "You scored 92 on this assessment. HIGH"

## TODO
* Build custom block for assessment score by form ID.
* Build custom block instead of shortcode for assessment results.
* Consider adding a WYSIWYG field to allow specific text if there are no results (haven't taken the assessment yet).
* ~~Build shortcode for assessment score by form ID.~~
* ~~Allow choosing forms from saved assessments (in the option value) to only show results for specific forms.~~ **Done!**
