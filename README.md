# atkuiautosaveform
This [Atk4\Ui\Form](https://github.com/atk4/ui) extension does 2 things:
1) it automatically submits if a user changes a control value, e.g. selects a dropdown or types some text into
a text input.
2) If a field value is updated within form submit (e.g. if Model::save() modifies field values before saving), 
the AutoSaveForm updates the matching controls accordingly.

To create a proper UI experience for the user, the save button of the form indicates the state of the form. It has 3 states:
1) *Initial*: Only a colored outline (using FUI`s "basic" class). This means that no value within the form was changed
2) *Highlighted*: A colored background. Indicates that changes to a value were detected.
3) *Highlighted and loading* The button is colored and has a loading animation on it. This indicates that the form
submission is happening.

See AutoSaveForm in action in [this video](https://youtu.be/t1n24NJnJX0).

Open `tests/autosaveformdemo.php` in your browser for a demo.

# Current status
### Tested with these Controls
- Line
- Textarea
- Checkbox
- Radio
- Calendar
- Dropdown
- Lookup (problem here, see below)

### Not tested with
- Multiline

# Known issues
- Lookup and adjusting control value if it was changed during form submit does not work at the moment. 
With dropdown, this problem does not exist. If your application does not change the data value of the
corresponding field, this issue does not cause problems. The automatic saving of Lookup values works.

# Usage
Just use `AutoSaveForm` instead of `\Atk4\Ui\Form` in your code. See `tests/autosaveformdemo.php` for an example.

# Installation
The easiest way to use this repository is to add it to your composer.json in the require section:
```json
{
  "require": {
    "philippgrashoff/atkuiautosaveform": "5.*"
  }
}
```
# Versioning
The version numbers of this repository correspond with the atk4\data versions. So 5.2.x is compatible with atk4\data 5.2.x and so on.
