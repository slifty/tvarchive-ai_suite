# Training and Testing Methodology

## Building training datasets
Training datasets are used to train models in various services.  The act of 
converting a training dataset into the format expected by a given service
is handled at the code level, not at the point of data definition.  This means
that some training data may be modified (e.g. keyframes extracted) or ignored
(e.g. negative examples not used if the service doesn't support them).
Training data is currently comprised of images and videos, although the data
definition format is flexible enough to eventually allow for the inclusion of
audio as well.

### Understanding the data
- Training data is comprised of both positive and negative examples.
- Each image or video clip must contain a single face looking at the camera.
- A variety of backgrounds, lightings, and contexts are used.
- The most important training data should appear first.
- Images cannot have more than one head (including people facing away from the camera)
- The face must not be obscured.
- The face should be directly pointed at the camera, not rotated to the side.

### Open Questions
- Are there merits to including a variety of video qualities?
- Is it possible to overtrain a model?

## Building gold datasets
Gold datasets are used to test existing models.  Similar to training sets, the
act of using a gold dataset to test a model is handled by code.

### Understanding the data
- Gold data is comprised of both positive and negative examples
- Content can contain any number of faces (e.g. no faces, or hundreds of faces)
- In the case of video, gold data should be considered positive if the face exists
	for ANY moment of a given second.  e.g. if the face exists at 50.5 seconds
	but not 50.1 seconds it would be marked as "true" for the 50 second mark.
- Uncategorized moments in time based media (e.g. a video or audio) are ignored.
- Gold data represents what a human would be able to identify correctly.  This
	means that there will be certain positive examples that are unlikely to be
	detected by even the most sophistocated algorithm.  These instances are
	tagged as "poor" examples, which should allow an evaluator to choose to
	ignore the poor data.

## Evaluating models
Once a model has been trained and gold data has been processed, a given grade for
an image or moment in the gold data test can be lumped into four categories:

1. True positives (the model correctly identified a face as existing)
2. False positives (the model incorrectly identified a face as existing)
3. True negatives (the model correctly identified a face as not existing)
4. False negatives (the model incorrectly identified a face as not existing)

These can then be summarized into a total accuracy score for both the positive
and negative case.  60% positive accuracy would mean that 60% of the time that a
face existed it was properly identified.  60% negative accuracy would mean that 60%
of the time a face did NOT exist it was improperly identified.

### Parameters for evaluation
- Certain systems provide confidence scores, which mean a cutoff can be selected
	as a "cutoff" for categorization.
- Certain systems provide 

### Outputs of evaluation
- Currently the only results output are accuracy scores, however we are
	exploring more granular forms of output as well.