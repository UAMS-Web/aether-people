Written against v0.5.4.

By the end of this page a researcher has a public profile page at `/researcher/<slug>`, and you will have seen the checks that decide whether that page appears.

It assumes the add-on is installed (see [Installation (README)](https://github.com/UAMS-Web/aether-people#installation)) in a Statamic 6 site that has the `people` collection, and that you can edit people entries in the Control Panel.

## 1. Open or create a person

In the Control Panel, open the People collection and create a person, or open an existing one. Fill in the person's name in the Person Name section, and note the slug in the sidebar.

## 2. Give the person a researcher signal

On the Research Role tab, fill in at least one of: `research_profile_url`, `research_interests`, `orcid_id`, `researcherid`, `scopus_author_id`, `google_scholar_author_id` or `pubmed_author_id`. Any one is enough.

**Note:** these seven handles are the default `researcher_profile.signals`. A host can change the list. See [Configuration](Configuration.md#researcher_profile).

## 3. Publish the person

Publish the entry. The page appears only for a published person whose `status_and_visibility` is not `private` and who is not password protected.

## 4. Visit the profile

Open `/researcher/<slug>` on the site. The `researcher` lens is active on every site, so the URL works under each site's own prefix, such as `/health/researcher/<slug>`. The page renders the `people/researcher/show` template with the person.

## 5. Remove the signal

Clear every signal field, save and reload the URL. The lens no longer passes, so the request falls through to Statamic's normal routing and the answer is a 404. Nothing on the response reveals that the person exists.

## 6. Link to the lens from a template

In an Antlers template that has the person's `slug` in context, print the lens URL:

```antlers
<a href="{{ people_lens_url lens='researcher' }}">Research profile</a>
```

The tag prints nothing for a lens that is not active on the current site. See [Tags and modifiers](Tags-and-modifiers.md#people_lens_url).

## Go further

- [Lenses](Lenses.md): the other four lenses and how a gate decides.
- [Merging duplicate people](Merging-duplicate-people.md): a guide for the editors who keep the collection clean.
- [Reference](Reference.md): every area the add-on adds.
