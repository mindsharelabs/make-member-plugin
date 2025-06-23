# Quick Guide: Adding Volunteer Schedules

## What Are Volunteer Schedules?

Volunteer schedules let you set regular weekly shifts for volunteers. The system then tracks whether volunteers sign in on time, early, late, or outside their scheduled hours.

## How to Add Volunteer Schedules

### Step 1: Access the Schedules Page

1. Log into WordPress admin
2. Go to **Volunteering → Schedules**

### Step 2: Add a New Schedule

1. Find the **"Add New Volunteer Schedule"** form at the top
2. Fill in the required fields:
   - **Volunteer:** Select from the dropdown (shows all volunteers who have volunteered before)
   - **Day of Week:** Choose Sunday through Saturday
   - **Start Time:** When their shift should begin (e.g., 10:00 AM)
   - **End Time:** When their shift should end (e.g., 2:00 PM)
3. Click **"Add Schedule"**

### Step 3: View Your Schedules

- **Weekly Grid:** Visual overview showing who's scheduled each day
- **Schedule Table:** Complete list of all volunteer schedules
- **Remove Schedules:** Use the "Remove" button to delete outdated schedules

## Example Schedules

```
Monday Morning Shift:
- Volunteer: Sarah Johnson
- Day: Monday
- Time: 9:00 AM - 1:00 PM

Wednesday Evening Shift:
- Volunteer: Mike Davis
- Day: Wednesday
- Time: 6:00 PM - 9:00 PM

Weekend Workshop Helper:
- Volunteer: Lisa Chen
- Day: Saturday
- Time: 10:00 AM - 4:00 PM
```

## How Schedule Tracking Works

When volunteers sign in, the system automatically checks:

- ✅ **On Time:** Within 15 minutes of scheduled start time
- ⏰ **Early:** More than 15 minutes before scheduled time
- ⏰ **Late:** More than 15 minutes after scheduled time
- 📅 **Unscheduled:** No regular schedule for this day/time

This information appears in:

- Volunteer sign-in messages
- Session summaries when signing out
- Admin reports and statistics

## Tips for Success

**Setting Realistic Schedules:**

- Start with volunteers who already come regularly
- Allow some flexibility - 15-minute tolerance is built in
- Consider the volunteer's availability and preferences

**Managing Schedules:**

- Review schedules monthly and remove outdated ones
- Add new schedules as volunteers establish regular patterns
- Use the weekly grid view to spot coverage gaps

**Communicating with Volunteers:**

- Let volunteers know their schedules have been added
- Explain that schedules help with planning but aren't strict requirements
- Emphasize that unscheduled volunteering is still welcome and valuable

## Troubleshooting

**Volunteer Not in Dropdown:**

- Only volunteers who have completed at least one session appear
- Make sure they've signed in as a volunteer at least once

**Schedule Not Saving:**

- Check that all fields are filled in
- Verify start time is before end time
- Ensure the volunteer hasn't already been scheduled for that exact time slot

**Schedule Tracking Not Working:**

- Verify database tables exist (Volunteering → System Test)
- Check that the schedule is marked as "Active"
- Confirm volunteer is signing in with the correct user account

## Next Steps

After adding schedules:

1. **Monitor Adherence:** Check volunteer reports to see schedule compliance
2. **Adjust as Needed:** Update schedules based on actual volunteer patterns
3. **Recognize Consistency:** Acknowledge volunteers who maintain regular schedules
4. **Plan Coverage:** Use schedule data to ensure adequate volunteer coverage

The schedule system helps create structure while maintaining the flexibility that makes volunteering enjoyable and sustainable.
