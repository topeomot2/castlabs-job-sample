# castlabs-job-sample
A simple php script for submitting a encoding/encryption job.

There are 3 stages to submitting a job.

1. Get a ticket url
2. Call the ticket url gotten from stage 1 and verify you have access to the vtk api. The actual ticket is returned.
3. Call the vtk job api with an Authorization Header, which contains the ticket from stage 2 and the json containing the definition of your job as the body of the POST operation.
